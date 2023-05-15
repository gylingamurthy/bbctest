<?php
/**
 * @file
 * Contains \Drupal\bbctest\Form\BbcsignupForm.
 */
namespace Drupal\bbctest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\Markup;

class BbcsignupForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bbctest_form';
  }

  /**
  * @var \Drupal\Core\Database\Connection
  */
   protected $conn;

       /**
       * @param \Drupal\Core\Database\Connection $connection
       */
   public function __construct(Connection $connection) {

     $this->conn = $connection;

   }

   public static function create(ContainerInterface $container) {
      return new static(
        $container->get('database'),
      );
    }

   public function buildForm(array $form, FormStateInterface $form_state) {

     $tempstore = \Drupal::service('tempstore.private')->get('bbctest_form');
     $uname = $tempstore->get('uname');
     $uemail = $tempstore->get('uemail');
     $sid = $tempstore->get('sid');

     $form['basic'] = array(
      '#type' => 'fieldset',
     );

    $form['basic']['student_name'] = array(
      '#type' => 'textfield',
      '#title' => t('User Name:'),
      '#required' => TRUE,
      '#element_validate' => array(array($this, 'validator_userid')),
      '#default_value' => ($uname) ? $uname : '',
    );

    $form['basic']['student_mail'] = array(
      '#type' => 'email',
      '#title' => t('Enter Email ID:'),
      '#required' => TRUE,
      '#default_value' => ($uemail) ? $uemail : '',
    );

    $form['basic']['student_rollno'] = array(
      '#type' => 'number',
      '#title' => t('Enter Enrollment Number:'),
      '#required' => TRUE,
      '#default_value' => ($sid) ? $sid : '',
    );

    $triggering_element = $form_state->getTriggeringElement();

    $form['basic']['course_subject'] = array (
      '#type' => 'select',
      '#title' => ('Select Subject:'),
      '#required' => TRUE,
      '#options' => array(
        'math' => t('Math'),
		    'science' => t('Science'),
        'art' => t('Art'),
        'language_art' => t('Language Art'),
      ),
      '#ajax' => [
        'callback' => [$this, 'reloadCourse'],
        'event' => 'change',
        'wrapper' => 'course-field-wrapper',
      ],
    );

    $var1 = $this->get_topic($triggering_element['#value']);

    $form['basic']['course_topic'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Topic'),
      '#prefix' => '<div id="course-field-wrapper">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'reloadTopic'],
        'event' => 'change',
        'wrapper' => 'topic-field-wrapper',
      ],
    ];

    $course = $form_state->getValue('course_subject');

    $form['basic']['course_timeslot'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Timeslot'),
      '#options' => $this->get_slot($triggering_element['#value'],$course),
      '#prefix' => '<div id="topic-field-wrapper">',
      '#suffix' => '</div>',

    ];

    $form['basic']['course_topic']['#options'] = ($var1);

    $form['basic']['actions']['#type'] = 'actions';
    $form['basic']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Add Course'),
      '#button_type' => 'primary',
    );

    $form['slot'] = array(
     '#type' => 'fieldset',
    );

    $form['slot']['trig'] = array(
     '#type' => 'item',
     '#markup' => $this->displayOutput($form),
    );

    $form['table'] = [
      '#type' => 'table',
      '#header' => array('Subject', 'Topic', 'Slot'),
      '#rows' => $this->showSlot($form),
      '#empty' => t('No content has been found.'),
    ];
    return $form;
  }

  public function reloadCourse(array $form, FormStateInterface $form_state) {
    $form['basic']['course_timeslot']['#prefix'] = '<div id="topic-field-wrapper">';
    return array($form['basic']['course_topic'],$form['basic']['course_timeslot']);
  }

  public function reloadTopic(array $form, FormStateInterface $form_state) {
    $form['basic']['course_timeslot']['#prefix'] = '<div id="topic-field-wrapper">';
    return $form['basic']['course_timeslot'];
  }

  private function displayOutput($form) {
      $fm = $form['basic']['student_rollno']['#default_value'];
      if($fm) {
        $result = $this->conn->select('pupil', 'n')
            ->fields('n', array('rollid', 'name', 'email'))
            ->condition('n.rollid', $fm, '=')
            ->execute()->fetchAllAssoc('rollid');

            $rows = array();
            foreach ($result as $row => $content) {
              $rows = array(
                'data' => array($content->rollid, $content->name, $content->email));
            }
            return 'Roll Id: '.$rows[data][0] .'</br>'.'  Name: '.$rows[data][1] .'</br>'.'  Email: '.$rows[data][2];
      } else {
        return t('No Data Available');
      }
  }

  private function showSlot($form) {
      $fm = $form['basic']['student_rollno']['#default_value'];
      if($fm) {
        $result = $this->conn->select('slot', 'n')
            ->fields('n', array('subject', 'topic', 'plan'))
            ->condition('n.rollid', $fm, '=')
            ->execute()->fetchAll();

            $rows = array();
            foreach ($result as $row => $content) {
              $rows[$i++] = array(
                'data' => array($content->subject, $content->topic, $content->plan));
            }


            return $rows;
      } else {
        return t('No Data Available');
      }
  }

  private function get_topic($course) {

    $map = [
      'math' => array('' => '--None-','algebra' => 'Algebra','trigonometry' =>'Trigonometry','calculus' => 'Calculus'),
      'science' => array('' => '--None-','physics' => 'Physics','chemistry' => 'Chemistry','biology' => 'Biology'),
      'art' => array('' => '--None-','art_history' => 'Art History','painting' => 'Painting','drawing' => 'Drawing'),
      'language_art' => array('' => '--None-','literature' => 'Literature','grammar' => 'Grammar','writing' => 'Writing'),

    ];

      return $map[$course];
    }

    private function get_slot($topic, $course) {
      if($course == 'math') {
        $map['math'] = [
          'algebra' => array('' => '--None-','8:00 AM' => '8:00 AM','11:00 AM' => '11:00 AM'),
          'trigonometry' => array('' => '--None-','9:00 AM' => '9:00 AM','12:00 AM' => '12:00 AM'),
          'calculus' => array('' => '--None-','10:00 AM' => '10:00 AM','3:00 PM' => '3:00 PM'),
        ];
      } else if ($course == 'science') {
        $map['science'] = [
          'physics' => array('' => '--None-','10:00 AM' => '10:00 AM','3:00 PM' => '3:00 PM'),
          'chemistry' => array('' => '--None-','9:00 AM' => '9:00 AM','1:00 PM' => '1:00 PM'),
          'biology' => array('' => '--None-','8:00 AM' => '8:00 AM','10:00 AM' => '10:00 AM'),
        ];
      } else if ($course == 'art') {
        $map['art'] = [
          'art_history' => array('' => '--None-','11:00 AM' => '11:00AM'),
          'painting' => array('' => '--None-','2:00 PM' => '2:00 PM'),
          'drawing' => array('' => '--None-','8:00 AM' => '8:00 AM','5:00 AM' => '5:00 AM'),
        ];
      } else if ($course == 'language_art') {
        $map['language_art'] = [
          'literature' => array('' => '--None-','8:30 AM' => '8:30 AM','11:45 AM' => '11:45 AM'),
          'grammar' => array('' => '--None-','8:00 AM' => '8:00 AM','9:00 AM' => '9:00 AM','10:00 AM' => '10:00 AM','11:00 AM' => '11:00 AM','1:00 PM' => '1:00 PM'),
          'writing' => array('' => '--None-','8:00 AM' => '8:00 AM','11:00 AM' => '11:00 AM'),
        ];
      }

        return $map[$course][$topic];
      }

      /**
       * This is the function used to validate
       */
      public function validator_userid($element, FormStateInterface $form_state) {


        if (!preg_match('/^[\w-]+$/', $element['#value'])) {
          $form_state->setErrorByName('student_userid', t('Enter only Alphanumeric Values'));
        }
      }

      public function validateForm(array &$form, FormStateInterface $form_state) {
        if(empty($form_state->getValue('course_topic'))) {
          $form_state->setErrorByName('course_topic', $this->t('Please select Topic'));
        }

        if(empty($form_state->getValue('course_timeslot'))) {
          $form_state->setErrorByName('course_timeslot', $this->t('Please select Timeslot'));
        }

        if($form_state->getValue('student_rollno')) {
          $fm = $form_state->getValue('student_rollno');
          $tm = $form_state->getValue('course_timeslot');
          $result = $this->conn->select('slot', 'n')
              ->fields('n', array('plan'))
              ->condition('n.rollid', $fm, '=')
              ->execute()->fetchAll();

              $rows = array();
              foreach ($result as $content) {
                if($tm == $content->plan) {
                  $form_state->setErrorByName('course_timeslot', $this->t('Please Select Different Timeslot, You Earlier selected this Timeslot'));
                }

              }

        }

      }

      /**
       * This is the function used to saving the data into database
       */

      public function submitForm(array &$form, FormStateInterface $form_state) {

          $tempstore = \Drupal::service('tempstore.private')->get('bbctest_form');
          $tempstore->set('uname', $form_state->getValue('student_name'));
          $tempstore->set('uemail', $form_state->getValue('student_mail'));
          $tempstore->set('sid', $form_state->getValue('student_rollno'));

          //Data base adding

          $sldata['rollid'] = $stdata['rollid'] = $form_state->getValue('student_rollno');
          $stdata['name'] = $form_state->getValue('student_name');
          $stdata['email'] = $form_state->getValue('student_mail');
          $sldata['subject'] = $form_state->getValue('course_subject');
          $sldata['topic'] = $form_state->getValue('course_topic');
          $sldata['plan'] = $form_state->getValue('course_timeslot');



          $result = $this->conn->merge('pupil')
              ->key(['rollid' => $stdata['rollid']])
              ->fields($stdata)->execute();

          $result = $this->conn->insert('slot')
              ->fields($sldata)->execute();

      }

}
