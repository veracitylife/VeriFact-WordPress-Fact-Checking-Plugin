<?php
$path=__DIR__.'/contracts/verifact-api-3.2-openapi.json';$failures=[];
$spec=json_decode((string)file_get_contents($path),true);
if(!is_array($spec)){$failures[]='OpenAPI contract must be valid JSON';}
if(($spec['info']['version']??'')!=='3.2.0'){$failures[]='API contract version must be 3.2.0';}
$required=['/api/v1/capabilities','/api/v1/auth/whoami','/api/v1/connection/test','/api/v1/check','/api/v1/check/batch','/api/v1/jobs','/api/v1/jobs/batch','/api/v1/jobs/{job_id}/retry','/api/v1/audit','/api/v1/audit/export'];
foreach($required as $route){if(!isset($spec['paths'][$route])){$failures[]='Missing API route: '.$route;}}
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}echo "VeriFact WordPress/API 3.2 contract checks passed.\n";