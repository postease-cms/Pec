<?php

/**
 * PecHttp (v1.2 Released at 02.Sep.2019)
 * ------------------------------------------------------------------
 * POSTEASE CLIENT (SDK FOR POSTEASE API)
 *
 *   This SDK sends an HTTP request to the POSTEASE API.
 *   You can send a GET request to retrieve a variety of data,
 *   send a POST request, and perform some actions, such as emailing.
 *   "Advanced cache" has been implemented in version 1.1.
 *
 * Copyright (c) 2018-present, khiten Inc. and POSTEASE Contributors.
 *
 *   Licensed under the Apache License, Version 2.0 (the "License");
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * ------------------------------------------------------------------
 */


class PecHttp
{
  /*
   * variables for get(common)
   */
  private $endpoint;
  private $resource;
  private $ssr = 1;
  private $key;
  private $params = array();
  private $response = array();
  private $response_type = 'Object';
  private $response_header = array();
  private $response_body = null;


  /*
   * advanced cache
   */
  private $use_advanced_cache = 0;
  private $allow_advanced_cache = 0;
  private $local_last_modified = 0;
  private $advanced_cache_path = null;
  private $advanced_cache_data_path = null;
  private $local_last_modified_path = null;
  private $advanced_cache_config = array(
    'dir_name'      => 'cache',
    'data_dir_name' => 'data',
    'last_modified_file_name' => 'last_modified.txt',
  );


  /*
   * variables for post_contact_send_mail
   */
  private $action;
  private $contact_target;
  private $contact_data;
  private $contact_items;
  private $config_mail;
  private $config_smtp;
  private $post_fields = array();


  /**
   * Constructor
   */
  public function __construct($endpoint = null, $advanced_cache = true)
  {
    if ($endpoint) $this->endpoint = $endpoint;
    if ($advanced_cache) $this->prepareAdvancedCache();
  }


  /**
   * Prepare Advanced Cache
   */
  private function prepareAdvancedCache()
  {
    // Get Advanced Cache
    $advances_cache = $this->get('advanced_cache', 'Array');
    if ($advances_cache['allow'] > 0)
    {
      $this->allow_advanced_cache = 1;

      // Prepare Advanced Cache
      $this->advanced_cache_path      = dirname(__FILE__) . "/{$this->advanced_cache_config['dir_name']}";
      $this->advanced_cache_data_path = $this->advanced_cache_path . "/{$this->advanced_cache_config['data_dir_name']}";
      $this->local_last_modified_path = $this->advanced_cache_path . "/{$this->advanced_cache_config['last_modified_file_name']}";
      if (false === file_exists($this->advanced_cache_path))      mkdir($this->advanced_cache_path, 0777);
      if (false === file_exists($this->advanced_cache_data_path)) mkdir($this->advanced_cache_data_path, 0777);

      // Check Local Last-modified
      if (false === file_exists($this->local_last_modified_path))
      {
        file_put_contents($this->local_last_modified_path, $advances_cache['last_modified']);
      }
      else {
        $this->local_last_modified = file_get_contents($this->local_last_modified_path);
        if (! empty($this->local_last_modified))
        {
          if (intval($this->local_last_modified) === intval($advances_cache['last_modified']))
          {
            $this->use_advanced_cache = 1;
          }
          else {
            // Set New Last-modified and Clear Cache files
            file_put_contents($this->local_last_modified_path, $advances_cache['last_modified']);
            if(file_exists($this->advanced_cache_data_path))
            {
              $cache_files = glob($this->advanced_cache_data_path . '/*');
              foreach ($cache_files as $file)
              {
                unlink($file);
              }
            }
          }
        }
        else {
          file_put_contents($this->local_last_modified_path, $advances_cache['last_modified']);
        }
      }
    }
  }


  /**
   * (For Debug)
   */
  public function get_use_advanced_cache()
  {
    return $this->use_advanced_cache;
  }


  /**
   * Set Endpoint
   * [GET/POST]
   * -----------------------------------------------
   * @param string $endpoint
   * @return object $this
   */
  public function set_endpoint($endpoint)
  {
    if ($endpoint)
    {
      $this->endpoint = $endpoint;
    }
    return $this;
  }


  /**
   * Set Resource
   * [GET/POST]
   * -----------------------------------------------
   * @param string $resource
   * @return object $this
   */
  public function set_resource($resource)
  {
    if ($resource)
    {
      $this->resource = $resource;
    }
    return $this;
  }


  /**
   * Set Params
   * [GET]
   * -----------------------------------------------
   * @param array $params
   * @return object $this
   */
  public function set_params($params)
  {
    if (! empty($params) && is_array($params))
    {
      $this->params = $params;
    }
    return $this;
  }


  /**
   * Set Key
   * [GET]
   * -----------------------------------------------
   * @param mixed $key
   * @return object $this
   */
  public function set_key($key)
  {
    if (! empty($key))
    {
      $this->key = $key;
    }
    return $this;
  }


  /**
   * Set Response Type
   * [GET]
   * -----------------------------------------------
   * @param mixed $response_type
   * @return object $this
   */
  public function set_response_type($response_type)
  {
    if (! empty($response_type))
    {
      $this->response_type = $response_type;
    }
    return $this;
  }


  /**
   * Generate Query String
   * [GET]
   * -----------------------------------------------
   * @return bool
   */
  private function generateQueryString()
  {
    $query_string = null;
    if (! empty($this->ssr))
    {
      $query_string .= ($query_string) ? '&' : '?';
      $query_string .= 'ssr=' . $this->ssr;
    }
    if (! empty($this->resource))
    {
      $query_string .= ($query_string) ? '&' : '?';
      $query_string .= 'resource=' . $this->resource;
    }
    if (! empty($this->key))
    {
      $query_string .= ($query_string) ? '&' : '?';
      $query_string .= 'key=' . $this->key;
    }
    if (! empty($this->params))
    {
      foreach ($this->params as $key => $value)
      {
        if (is_array($value)) $value = implode(',', $value);
        $query_string .= ($query_string) ? '&' : '?';
        $query_string .= "params[{$key}]=" . preg_replace('/\s/', '', $value);
      }
    }
    return $query_string;
  }


  /**
   * Get Aliases
   */
  public function get_archives($response_type = null)
  {
    $this->resource = 'archives';
    return $this->get('archives', $response_type);
  }

  public function get_categories($response_type = null)
  {
    $this->resource = 'categories';
    return $this->get('categories', $response_type);
  }

  public function get_comment($response_type = null)
  {
    $this->resource = 'comment';
    return $this->get('comment', $response_type);
  }

  public function get_comments($response_type = null)
  {
    $this->resource = 'comments';
    return $this->get('comments', $response_type);
  }

  public function get_contact_items($response_type = null)
  {
    $this->resource = 'contact_items';
    return $this->get('contact_items', $response_type);
  }

  public function get_image_frames($response_type = null)
  {
    $this->resource = 'image_frames';
    return $this->get('image_frames', $response_type);
  }

  public function get_languages($response_type = null)
  {
    $this->resource = 'languages';
    return $this->get('languages', $response_type);
  }

  public function get_post($response_type = null)
  {
    $this->resource = 'post';
    return $this->get('post', $response_type);
  }

  public function get_posts($response_type = null)
  {
    $this->resource = 'posts';
    return $this->get('posts', $response_type);
  }

  public function get_posttypes($response_type = null)
  {
    $this->resource = 'posttypes';
    return $this->get('posttypes', $response_type);
  }

  public function get_sites($response_type = null)
  {
    $this->resource = 'sites';
    return $this->get('sites', $response_type);
  }

  public function get_tags($response_type = null)
  {
    $this->resource = 'tags';
    return $this->get('tags', $response_type);
  }


  /**
   * Execute Get Request
   * -----------------------------------------------
   * [@param string $resource]
   * [@param string $response_type]
   * @return array | object
   */
  public function get($resource = null, $response_type = null)
  {
    if ($resource) $this->resource = $resource;
    if ($response_type) $this->response_type = $response_type;
    $this->response_type = (strtolower($this->response_type) == 'array') ? true : false;

    // Check Resource (are set)
    if ($this->resource)
    {
      // Check Endpoint (are set)
      if ($this->endpoint)
      {
        // Check Preview (avoid making cache file when preview)
        $preview = ((key_exists('preview', $this->params) && $this->params['preview'] == 1) || (! empty($this->key) && preg_match('/^[a-z0-9]{32}$/', $this->key))) ? true : false;

        // Check and Use Advanced Cache
        $advanced_cache_file_name = hash('tiger128,4', $this->generateQueryString()) . '.json';
        if (! $preview && $this->use_advanced_cache && file_exists($this->advanced_cache_data_path . "/{$advanced_cache_file_name}"))
        {
          $this->response_body = file_get_contents($this->advanced_cache_data_path . "/{$advanced_cache_file_name}");
          return json_decode($this->response_body, $this->response_type);
        }
        else {
          try{
            // Check Connection
            if (false === $ch = curl_init($this->endpoint . $this->generateQueryString()))
            {
              return $this->generateErrorResponse(0, 'Connection failed');
            }
            else {
              $http_header = array(
                'Content-Type: application/json',
              );
              curl_setopt_array($ch,
                array(
                  CURLOPT_HEADER => true,
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_TIMECONDITION => CURL_TIMECOND_IFMODSINCE,
                  CURLOPT_HTTPHEADER => $http_header,
                )
              );
              $this->response = curl_exec($ch);
              $info = curl_getinfo($ch);
              $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
              $this->response_header = substr($this->response, 0, $header_size);
              $this->response_body = substr($this->response, $header_size);

              // Curl Error
              if (curl_errno($ch))
              {
                return $this->generateErrorResponse($info['http_code'], curl_error($ch));
              }

              // Response Not 2xx, 3xx
              $decoded_body = json_decode($this->response_body, true);
              if (empty($decoded_body['hasError']) && $info['http_code'] >= 400)
              {
                if ($info['http_code'] == '404')
                {
                  return $this->generateErrorResponse((string)$info['http_code'], 'Invalid endpoint (Check URL)');
                }
                else
                {
                  return $this->generateErrorResponse($info['http_code'], 'An unexpected error occurred');
                }
              }
              curl_close($ch);

              // Response 2xx, 3xx
              if (! $preview && $this->use_advanced_cache) file_put_contents($this->advanced_cache_data_path . "/{$advanced_cache_file_name}", $this->response_body);
              return json_decode($this->response_body, $this->response_type);
            }
          }
          catch (Exception $e)
          {
            return $this->generateErrorResponse(0, 'An exception occurred');
          }
        }
      }
      else {
        return $this->generateErrorResponse(0, 'Endpoint not set');
      }
    }
    return $this->generateErrorResponse(0, 'Resource not set');
  }


  /**
   * Set Contact Target
   * [POST DATA / SEND MAIL]
   * -----------------------------------------------
   * @param array $contact_target
   * @return $this
   */
  public function set_contact_target($contact_target)
  {
    if (! empty($contact_target) && is_array($contact_target))
    {
      $this->contact_target = $contact_target;
    }
    return $this;
  }


  /**
   * Set Contact Data
   * [POST DATA / SEND MAIL]
   * -----------------------------------------------
   * @param array $data
   * @return $this
   */
  public function set_data($data)
  {
    return $this->set_contact_data($data);
  }


  /**
   * Set Contact Data
   * [POST DATA / SEND MAIL]
   * -----------------------------------------------
   * @param array $contact_data
   * @return $this
   */
  public function set_contact_data($contact_data)
  {
    if (! empty($contact_data) && is_array($contact_data))
    {
      $this->contact_data = $contact_data;
    }
    return $this;
  }


  /**
   * Set Contact Items
   * [POST DATA / SEND MAIL]
   * -----------------------------------------------
   * @param array $contact_items
   * @return $this
   */
  public function set_contact_items($contact_items)
  {
    if (! empty($contact_items) && is_array($contact_items))
    {
      $this->contact_items = $contact_items;
    }
    return $this;
  }


  /**
   * Set Config Mail
   * [POST DATA / SEND MAIL]
   * -----------------------------------------------
   * @param array $config_mail
   * @return $this
   */
  public function set_config_mail($config_mail)
  {
    if (! empty($config_mail) && is_array($config_mail))
    {
      $this->config_mail = $config_mail;
    }
    return $this;
  }


  /**
   * Set Config SMTP
   * [POST DATA / SEND MAIL]
   * -----------------------------------------------
   * @param array $config_smtp
   * @return $this
   */
  public function set_config_smtp($config_smtp)
  {
    if (! empty($config_smtp) && is_array($config_smtp))
    {
      $this->config_smtp = $config_smtp;
    }
    return $this;
  }


  /**
   * Generate PostFields
   * [POST DATA / SEND MAIL]
   * -----------------------------------------------
   * @return string
   */
  private function generatePostFields()
  {
    if (! empty($this->ssr))
    {
      $this->post_fields['ssr'] = $this->ssr;
    }
    if (! empty($this->action))
    {
      $this->post_fields['action'] = $this->action;
    }
    if (! empty($this->contact_target))
    {
      $this->post_fields['contact_target'] = $this->contact_target;
    }
    if (! empty($this->contact_data))
    {
      $this->post_fields['contact_data'] = $this->contact_data;
    }
    if (! empty($this->contact_items))
    {
      $this->post_fields['contact_items'] = $this->contact_items;
    }
    if (! empty($this->config_mail))
    {
      $this->post_fields['config_mail'] = $this->config_mail;
    }
    if (! empty($this->config_smtp))
    {
      $this->post_fields['config_smtp'] = $this->config_smtp;
    }
    return http_build_query($this->post_fields);
  }


  /**
   * Post Contact Send Mail
   * (Use post_data_send_mail)
   * [POST DATA / SEND MAIL]
   * -----------------------------------------------
   * @param string $response_type
   * @return array
   */
  public function post_contact_send_mail($response_type = null)
  {
    $this->resource = 'contact';
    $this->action = 'post_contact_send_mail';
    return $this->post_data_send_mail($response_type);
  }


  /**
   * Send Mail
   * (Use post_data_send_mail)
   * [POST DATA / SEND MAIL]
   * -----------------------------------------------
   * @param string $response_type
   * @return array
   */
  public function send_mail($response_type = null)
  {
    $this->resource = '';
    $this->action = 'send_mail';
    return $this->post_data_send_mail($response_type);
  }


  /**
   * Post Data Send Mail
   * -----------------------------------------------
   * @param string $response_type
   * @return array
   */
  public function post_data_send_mail($response_type = null)
  {
    if ($response_type) $this->response_type = (strtolower($response_type) == 'array') ? true : false;

    // Check Endpoint (are set)
    if ($this->endpoint)
    {
      try{
        // Check Connection
        if (false === $ch = curl_init($this->endpoint))
        {
          return $this->generateErrorResponse(0, 'Connection failed', 'post');
        }
        else {
          curl_setopt_array($ch,
            array(
              CURLOPT_POST => true,
              CURLINFO_HEADER_OUT => true,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_POSTFIELDS => $this->generatePostFields('post_contact_send_mail'),
            )
          );
          $this->response = curl_exec($ch);
          $info = curl_getinfo($ch);

          // Curl Error
          if (curl_errno($ch))
          {
            return $this->generateErrorResponse($info['http_code'], curl_error($ch), 'post');
          }

          curl_close($ch);
          return json_decode($this->response, $this->response_type);
        }
      }
      catch (Exception $e)
      {
        return $this->generateErrorResponse(0, 'An exception occurred', 'post');
      }
    }
    else {
      return $this->generateErrorResponse(0, 'Endpoint not set', 'post');
    }
  }


  /**
   * Generate Error Response
   * -----------------------------------------------
   * @param int $http_status_code
   * @param string $error_massage
   * @param string $method
   * @return array
   */
  private function generateErrorResponse($http_status_code = 0, $error_massage = '', $method = 'get')
  {
    $resource =  ($this->resource) ? $this->resource : '';
    return $error = array(
      'hasError'         => 1,
      'http_status_code' => $http_status_code,
      'error_massage'    => $error_massage,
      'method'           => $method,
      'resource'         => $resource,
    );
  }

}