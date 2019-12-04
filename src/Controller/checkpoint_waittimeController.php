<?php
    namespace Drupal\checkpoint_waittime\Controller;

    use Drupal\Core\Controller\ControllerBase;
    use Drupal\Core\Url;
    use Drupal\Core\Link;
    use Drupal\Component\Serialization\Json;
    use Drupal\Core\Config\Config;
    use Symfony\Component\HttpFoundation\JsonResponse;

    class checkpoint_waittimeController extends ControllerBase
    {
        public function checkpoint_waittime_api_v1_handler($first, $second)
        {
            if ($first == NULL and $second == NULL) {
                return $this->checkpoint_waittime_api_v1_documentation();
            }
            else{
                if ($first != NULL and $second == NULL) {
                    $arg_count = 1;
                }
                if ($first != '' and $second != '') {
                    $arg_count = 2;
                }
                $args = array($first, $second);
                $last_arg = $args[$arg_count - 1];
                if (strpos($last_arg, '.')) {
                  list ($last_arg, $format) = explode('.', $last_arg);
                }
                else {
                  $format = 'json';
                }
                $args[$arg_count - 1] = $last_arg;
                return $this->checkpoint_waittime_api_v1_data($args[0], $args[1], $format);
            }
        }
        public function checkpoint_waittime_api_v1_documentation()
        { 
            $build ['#title'] = 'Checkpoint Waittime API v1 Documentation';
            $build ['#markup'] = '<p>This API provides data on the TSA checkpoint waittimes.</p>';
            $build ['#markup'] .= '<p>By default all callbacks are returned in JSON format, they do not require the ".json" extension but it is encouraged. Additional return formats may be provided in the future.</p>';
            $build ['#markup'] .= 'Available Callbacks';
            $build ['#markup'] .= '<p>Checkpoint Waittime Data</p>';
            $airport_pattern = $GLOBALS['base_url']."/api/checkpoint_waittime/v1/airport_code.json";
            $airport_example = $GLOBALS['base_url'].'/api/checkpoint_waittime/v1/BOS.json';
            $day_pattern = $GLOBALS['base_url'].'/api/checkpoint_waittime/v1/week_day.json';
            $day_example = $GLOBALS['base_url'].'/api/checkpoint_waittime/v1/Tuesday.json';;
            $build['#markup'] .= "The data for the Airport on that day.<br/>
            Patterns: $airport_pattern, <a href='$airport_example'>$airport_example</a>,<br/><br/>
            $day_pattern, <a href='$day_example'>$day_example</a>";
            return $build;
        }
        public function checkpoint_waittime_api_v1_data($airport_code, $week_day, $format = 'json') {
            $cid = __function__ . ':' . $airport_code . ':' . $week_day;
            // check to see if there is anything in cache
            if ($cache = \Drupal::cache()->get($cid)) {
              $return = $cache->data;
            }
            else {
              $lock = \Drupal::lock();
              if (!$lock->acquire($cid)) {
                $lock->wait($cid, 1);
                $this->checkpoint_waittime_api_v1_data($airport_code, $week_day, $format);
              }
              else {
                $query = db_select('checkpoint_waittime_data')
                  ->fields('checkpoint_waittime_data')
                  ->condition('airport_code', $airport_code);
                if ($week_day) {
                  $query->condition('day', $week_day);
                }
                $query->orderBy('hour');
                $result = $query->execute();
                $data = array();
                $name = NULL;
                while ($item = $result->fetchObject()) {
                  $name = $item->airport_name;
                  unset($item->id);
                  unset($item->airport_name);
                  unset($item->airport_code);
                  $data[] = $item;
                }
                $return = array(
                  'airport_code' => strtoupper($airport_code),
                  'airport_name' => $name,
                  'count' => count($data),
                );
                if ($week_day) {
                  $return['day'] = ucfirst($week_day);
                }
                $return['data'] =  $data;
                \Drupal::cache()->set($cid, $return);
              }
            }
            return $this->checkpoint_waittime_api_v1_return_format($return, $format);
          }
          
          /**
           * Allow for formats to be easily added in the future
           */
          public function checkpoint_waittime_api_v1_return_format(Array $data, $format = 'json') {
             switch ($format) {
              case 'array':
                return $data;
              default:
                return new JsonResponse($data);
            }
          }
    }