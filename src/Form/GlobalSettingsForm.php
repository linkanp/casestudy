<?php

namespace Drupal\casestudy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Admin configuration form for sidebar
 *
 * @author shoeb
 */
class GlobalSettingsForm extends ConfigFormBase {

    /**
     * {@inheritdoc}  
     */
    protected function getEditableConfigNames() {
        return [
            'casestudy.global_settings',
        ];
    }

    /**
     * {@inheritdoc}  
     */
    public function getFormId() {
        return 'apha_casestudy_global_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->config('casestudy.global_settings');
        //print_r($config);

        $form['validation_error_message'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Case Study or Quiz form validation Error Message'),
            '#default_value' => $config->get('validation_error_message'),
        ];


        return parent::buildForm($form, $form_state);
    }
    
    /**
     * {@inheritdoc}  
     */
    public function submitForm( array &$form, FormStateInterface $form_state ) {
        parent::submitForm($form, $form_state);


        $this->config('casestudy.global_settings')
                ->set('validation_error_message', $form_state->getValue(['validation_error_message']))
                ->save();
    }

}
