<?php

/**
 * @file
 * Provides Microsoft Translator plugin controller.
 */

class TMGMTMicrosoftTranslator extends TMGMTDefaultTranslatorPluginController {

  /**
   * Bing API ID. - @todo this is maybe also in configuration, check?
   * 
   * @var string 
   */
  protected $bingAPIID = 'DA903AE0DF7668FD33F53192B5382E4D1D2CF369';
  
  // @todo - or maybe this is configurable optin - chech how we deal with 
  // configurations.
  protected $translatorURL = 'http://api.microsofttranslator.com/v2/Http.svc';
  
  /**
   * Implements TMGMTTranslatorPluginControllerInterface::canTranslation().
   * 
   * We will check are source and target language supported.
   */
  public function canTranslate(TMGMTJob $job) {
    $languages = $this->getSupportedLanguages();
    if (isset($languages[$job->source_language]) && isset($languages[$job->target_language])) {
      return TRUE;
    }

    // @todo - What to return if we can not query for what ever reason?
    return FALSE;
  }

  /**
   * Implements TMGMTTranslatorPluginControllerInterface::requestTranslation().
   * 
   * Here we will acutally query source and get translations.
   */
  public function requestTranslation(TMGMTJob $job) {
    // @todo - who is handling cron||batch here, we or some other controler?
    
    $translate_url = $this->translatorURL . '/Translate';
    
    foreach ($job->getItems() as $item) {
      /* @var $item TMGMTJobItem */
      $data = tmgmt_flatten_data($item->getSourceData());
      foreach ($data as $key => $value) {
        if ($value['#translate'] == FALSE) {
          continue;
        }
        
        $query_data = drupal_http_build_query(array(
          'appId'       => $this->bingAPIID,
          'from'        => $job->source_language,
          'to'          => $job->target_language,
          'contentType' => 'text/plain', // @todo - we probably need to have this 
                                        // changed based on context, because we
                                        // need to support html also.
          'text'        => $value['#text'], // @todo - if length is more then 
                                          // 10000 chars we need to split request.
        ));

        $options = array(
          // @todo - for some reason post is returning 202 status and service is not translating things, maybe check this later.
          //'method' => 'POST',
          //'data' => $query_data,
          'headers' => array('Content-Type' => 'text/plain')
        );
        $result = drupal_http_request($translate_url. '?' . $query_data, $options);

        switch ($result->code) {
          case 200:
            // Lets leave DOMDocument for now because this service enable us
            // to send array of translation sources, and we will probably use
            // this soon.
            $dom = new DOMDocument;
            $dom->loadXML($res->data);
            $string_items = $dom->getElementsByTagName('string');
            $data[$key]['#text'] = $string_items->item(0)->nodeValue;
            break;
          case 400:
            return new TMGMTTranslatorResponse(NULL, $result->data);
            break;
          default:
            // @todo - we need to think here more what to do if service is not
            // responding for some items and is responding for some others.
            return new TMGMTTranslatorResponse(NULL, $result->data);
            break;
        }
      }
      
      $item->addTranslatedData(tmgmt_unflatten_data($data));
    }
    
    $job->save();
    
    return new TMGMTTranslatorResponse(TMGMTJob::STATE_REVIEW, t('Microsoft Translator translation completed.'));
  } 

  /**
   * Returns array of service supported languages.
   * 
   * @return array
   */
  public function getSupportedLanguages() {
    $languages = &drupal_static(__FUNCTION__);
    
    if (!isset($languages)) {
      $languages = array();

      $res = drupal_http_request($this->translatorURL . '/GetLanguagesForTranslate' . '?appId=' . $this->bingAPIID);
      if ($res->code == 200) {
        $dom = new DOMDocument;
        $dom->loadXML($res->data);
        $string_items = $dom->getElementsByTagName('string');
        foreach($string_items as $item) {
          $languages[$item->nodeValue] = $item->nodeValue;
        }
      }
    }
    
    return $languages;
  }
  
}