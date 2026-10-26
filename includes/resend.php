<?php
// Sends email through the Resend service.
declare(strict_types=1);
require_once __DIR__.'/config.php';require_once __DIR__.'/external_api.php';
// Sends one email through Resend.
function resend_send_email(string $to,string $subject,string $text,string $html=''): array { $config=app_config();$key=trim((string)($config['resend_api_key']??''));$from=trim((string)($config['resend_from_email']??''));if($key===''||$from==='')return ['ok'=>false,'reason'=>'not_configured'];if(!filter_var($to,FILTER_VALIDATE_EMAIL)||mb_strlen($subject)>180)return ['ok'=>false,'reason'=>'invalid_input'];$result=external_json_post('https://api.resend.com/emails',['from'=>$from,'to'=>[$to],'subject'=>$subject,'text'=>$text,'html'=>$html!==''?$html:null],['Authorization: Bearer '.$key,'Idempotency-Key: imali-'.bin2hex(random_bytes(16))]);if(!$result['ok'])return ['ok'=>false,'reason'=>'provider_error'];$id=(string)($result['data']['id']??'');if($id==='')return ['ok'=>false,'reason'=>'missing_provider_id'];return ['ok'=>true,'provider_id'=>$id,'status'=>'accepted_not_delivered'];}
