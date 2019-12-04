<?php 

 namespace Drupal\checkpoint_waittime\Form;

 use Drupal\Core\Form\FormStateInterface;
 use Drupal\csv_importer\Form\ImporterForm;
 use Drupal\file\Entity\File;

 class CheckpointwaittimeForm extends ImporterForm  {
    public function getFormId(){
        return 'csv_importer_form'; 
    }
    public function getEditableConfigNames(){
        return ['csv_importer.form'];  
    }
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildform($form, $form_state);
        $form['importer']['entity_type']['#options']['checkpoint_waittime_data'] = "CheckPoints Wait Time Processor";
        $form['importer']['csv']['#upload_location'] =  'public://importer/';
       return $form;
    }
    public function submitForm(array &$form, FormStateInterface $form_state) {
        if ($form_state->getValue('entity_type') == 'checkpoint_waittime_data'){
            $fid = $form_state->getValue('csv');
            $file = File::load($fid[0]);
            $data = $this->csvtoarray($file);
            $batch = [
                'operations' => [
                  ['checkpoint_waittime', [$data]],
                ],
                'finished' => 'import_airport_batch_finished',
                'title' => t('Importing Airport'),
                'init_message' => t('Import is starting.'),
                'progress_message' => t('Importing...'),
                'error_message' => t('Import Airport has encountered an error.'),
          'file' => drupal_get_path('module', 'checkpoint_waittime').'/checkpoint_waittime.mybatch.inc',
              ];
            // $batch = array(
            //     'title' => t('Importing Data...'),
            //     'operations' => array(
            //         array(
            //           '\Drupal\checkpoint_waittime\ImportItem::add',
            //           array($data)
            //         ),
            //       ),
            //     'init_message' => t('Import is starting.'),
            //     'progress_message' => $this->t('Importing...'),
            //     'finished' => '\Drupal\checkpoint_waittime\ImportItem::ItemCallback',
            //   );
              batch_set($batch);
        }
        else{
            parent::submitForm($form, $form_state);
        }
    }
    public function csvtoarray($file){
        $file_name = $file->getFileUri();
        if(!file_exists($file_name) || !is_readable($file_name)) return FALSE;
        $data = array();
        if ($file = fopen($file_name, "r")) {
            $header = fgetcsv($file);
            $header[0] = 'airport';
            $header[1] = 'name';
            $header[2] = 'day of week';
            foreach ($header as $i => $title) {
              $header[$i] = trim(strtolower($title));
              if ($i > 3 && !strpos($header[$i], 'wait time')) {
                if ($i % 2) {
                  // odd values
                  $header[$i] .=  ' max standard wait time';
                }
                else {
                  // even values
                  $header[$i] .=  ' max precheck wait time';
                }
              }
            }
          while (($row = fgetcsv($file)) !== FALSE)
          {
              if(trim($row[0]) != ''){
                $data[] = array_combine($header, $row);
              }
          }
          fclose($file);
        }
        return $data;
      }
    
  }