<?php

namespace App\Controllers;
class Home extends BaseController
{
    private $base_url;
    private $api_token; 
    private $school_id;
    private $client;
  
    public function __construct()
    {
      $this->base_url = 'https://api.wonde.com/v1.0';
      $this->api_token = '2302e6c82cb36c3ad619d9372b9f6808ba2a560c'; ///needs to move to env variable
      $this->school_id = 'A1930499544'; 
      $this->client = new \Wonde\Client($this->api_token);
      $this->school = $this->client->school($this->school_id);
    }

    public function index()
    {
      $auth = $this->authentication();
      if ($auth->code != 200)
      {
        $this->error($auth->code,$auth->msg);
      }

      // school details
      $school = $this->client->schools->get($this->school_id);
      $data['school'] = $school;

      // teachers
      $teachers = array();
      foreach ($this->school->employees->all(['employment_details','classes'], ['has_class'=>'1']) as $employee) 
      {
        $is_current = $employee->employment_details->data->current;
        $is_teacher = $employee->employment_details->data->teaching_staff;
        if ($is_current && $is_teacher)
        {
          $teachers[] = $employee;
        }
      }

      // order by surname 
      array_multisort(array_column($teachers,'surname'),SORT_ASC,$teachers);
      $data['teachers'] = $teachers;
      return view('school_homepage',$data);
    }

    public function teacher_classes()
    {
      $teacher_id = ($_POST['teacher_id']) ? $_POST['teacher_id'] : FALSE;
      $teacher_mis_id = ($_POST['teacher_mis_id']) ? $_POST['teacher_mis_id'] : FALSE;
      if ( ! $teacher_id || ! $teacher_mis_id)
      {
        $this->error(404,'Requires a Teacher id');
      }      

      $teacher = $this->school->employees->get($teacher_id);
      $data['teacher'] = $teacher;

      $class_mis_ids = array();
      foreach ($this->school->employees->all(['classes'], ['only_mis_ids'=>$teacher_mis_id]) as $teacher) 
      {
        foreach ($teacher->classes->data as $key=>$tc)
        {
         $class_mis_ids[] = $tc->mis_id;
        }
      }

      $mis_id_string = implode(",",$class_mis_ids);
      $classes = array();
      foreach ($this->school->classes->all([], ['only_mis_ids'=>$mis_id_string,'has_students'=>'1']) as $class) 
      {
        foreach ($teacher->classes->data as $key=>$tc)
        {
          if ( ! isset($classes[$tc->id]))
          {
            $classes[$tc->id] = $tc;
          }
        }
      }
      $data['classes'] = $classes;
      return view('class_list',$data);
    }

    private function authentication()
    {
      try {
        // Initialize a cURL session
        $curl = curl_init();

        // Set cURL options
        curl_setopt($curl, CURLOPT_URL, $this->base_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->api_token,
        ]);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        // Execute the cURL request
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $response = json_decode($response);

        if ($http_code != 200) 
        {
          curl_close($curl);
          if ($response->error)
          {
            return (object) array('code'=>$http_code,'msg'=>($response->error_description) ? $response->error_description : $response->error);
          }
          else 
          {
            throw new \Exception("ERROR: There has been an issue with this authentication. Please check your entries and try again.");
          }
        }
        else 
        {
          return (object) array('code'=>$http_code,'msg'=>'Success');
        }
      }
      catch (\Exception $e) {
       return (object) array('code'=>403,'msg'=>$e->getMessage());
      }
    }

    private function error($code,$msg)
    {
      return view('custom_error',array('code'=>$code,'msg'=>$msg));
    }
}
