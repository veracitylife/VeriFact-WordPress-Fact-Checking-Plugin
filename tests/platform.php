<?php
$root=dirname(__DIR__);$path=$root.'/includes/class-verifact-platform.php';$failures=[];
$expect=static function(bool $condition,string $message)use(&$failures):void{if(!$condition){$failures[]=$message;}};
$expect(is_file($path),'Platform module is required');
$platform=is_file($path)?file_get_contents($path):'';
foreach([
    '/platform/claims','/platform/review-cases','/platform/providers','/platform/receipts/verify','/platform/history/','/platform/policies','/platform/deployment','/platform/entitlements','/platform/conformance','/platform/transparency'
] as $route){$expect(str_contains($platform,$route),'Missing WordPress platform route: '.$route);}
foreach([
    '/api/v1/claims/registry','/api/v1/review-cases','/api/v1/providers','/api/v1/receipts/verify','/evidence-map','/api/v1/policy-packs','/api/v1/deployment','/api/v1/entitlements','/api/v1/integrations/conformance','/api/v1/transparency'
] as $endpoint){$expect(str_contains($platform,$endpoint),'Missing VeriFact API platform contract: '.$endpoint);}
$expect(str_contains($platform,"current_user_can('verifact_manage')"),'Platform routes require VeriFact management capability');
$expect(!str_contains($platform,'VERIFACT_API_KEY'),'Platform module must use the core secret-safe transport');
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}echo "VeriFact 3.3 platform checks passed.\n";
