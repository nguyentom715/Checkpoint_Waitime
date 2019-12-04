<?php
use Symfony\Component\HttpFoundation\RedirectResponse;

function checkpoint_waittime($data, &$context){
  foreach ($data as $airport) {
    $airport_key = str_replace('"',"",array_keys($airport));
    $airport_key = str_replace("\0", "",$airport_key);
    $airport_value = str_replace("\0", "",array_values($airport));
    $airport_value = str_replace('"', "",$airport_value);
    $pos = is_numeric($airport_key[3]) == TRUE ? 3 : 4;
    for($i=$pos; $i<= count($airport_value)-2; $i += 2){
      if(check_aiport_code($airport_key, $airport_value, $i)){
          update_airport($airport_key, $airport_value, $i);
      }
      else {
          insert_airport($airport_key, $airport_value, $i);
      }
      $context['message'] = 'Importing airport...';
    }
  }
  $context['results'] = count($data)*24;
}

function import_airport_batch_finished($success, $results, $operations) {
    if ($success) {
      drupal_set_message(t('@count items imported.',['@count' => $results]));
    }
    $url = $GLOBALS['base_url']."/api/checkpoint_waittime/v1";
    $response = new RedirectResponse("$url");
    return $response->send();
  }

function update_airport($airport_key, $airport_value, $i){
  \Drupal::database()->update('checkpoint_waittime_data')
    ->fields(array(
        'max_precheck_wait' => (int) $airport_value[$i],
        'max_standard_wait' => (int) $airport_value[$i+1],
        'updated' => \Drupal::time()->getRequestTime(),
    ))
    ->condition('airport_code', $airport_value[0], '=')
    ->condition ('day', $airport_value[2])
    ->condition('hour', (int)substr($airport_key[$i],0,2), '=')
    ->execute();
}

function insert_airport($airport_key, $airport_value, $i) {
  \Drupal::database()->insert('checkpoint_waittime_data')
    ->fields(array(
        'airport_code' => $airport_value[0],
        'airport_name' => $airport_value[1],
        'day' => $airport_value[2],
        'hour' =>(int)substr($airport_key[$i],0,2),
        'max_precheck_wait' => (int) $airport_value[$i],
        'max_standard_wait' => (int) $airport_value[$i+1],
        'updated' => \Drupal::time()->getRequestTime(),
    ))
    ->execute();
}

function check_aiport_code($airport_key, $airport_value, $i){
  $query = \Drupal::database()->select('checkpoint_waittime_data', 'data');
  $query-> addField ('data', 'id');
  $query->condition ('data.airport_code', $airport_value[0]);
  $query->condition ('data.day', $airport_value[2]);
  $query->condition ('data.hour', (int)substr($airport_key[$i],0,2));
  return $query->execute()->fetchCol();
}