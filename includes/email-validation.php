<?php
// Checks an email address, and can ask an outside service if one is set up.
declare(strict_types=1);
require_once __DIR__.'/config.php';require_once __DIR__.'/external_api.php';
// Checks an email with an outside service, only if one is set up.
function validate_email_with_configured_provider(string $email): array { $template=trim((string)(app_config()['email_validation_url']??''));if($template==='')return ['status'=>'not_configured'];if(!str_contains($template,'{email}'))return ['status'=>'invalid_configuration'];$url=str_replace('{email}',rawurlencode($email),$template);$result=external_json_get($url,3600,'email-validation-'.hash('sha256',$email));if(!$result['ok'])return ['status'=>'unavailable'];$data=$result['data'];foreach(['valid','is_valid','deliverable'] as $key){if(array_key_exists($key,$data)&&is_bool($data[$key]))return ['status'=>$data[$key]?'valid':'invalid'];}if(isset($data['status'])&&is_string($data['status'])){if(in_array(strtolower($data['status']),['valid','deliverable','ok'],true))return ['status'=>'valid'];if(in_array(strtolower($data['status']),['invalid','undeliverable','disposable'],true))return ['status'=>'invalid'];}return ['status'=>'unknown'];}
