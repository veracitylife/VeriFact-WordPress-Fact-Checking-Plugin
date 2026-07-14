<?php
$path=__DIR__.'/contracts/verifact-api-3.5-openapi.json';$failures=[];
$spec=json_decode((string)file_get_contents($path),true);
if(!is_array($spec)){$failures[]='OpenAPI contract must be valid JSON';}
if(($spec['info']['version']??'')!=='3.5.0'){$failures[]='API contract version must be 3.5.0';}
$required=['/api/v1/capabilities','/api/v1/auth/whoami','/api/v1/connection/test','/api/v1/check','/api/v1/check/batch','/api/v1/jobs','/api/v1/jobs/batch','/api/v1/jobs/{job_id}/retry','/api/v1/audit','/api/v1/audit/export','/api/v1/claims/registry','/api/v1/claims/registry/search','/api/v1/review-cases','/api/v1/providers','/api/v1/receipts/verify','/api/v1/history/{history_id}/evidence-map','/api/v1/policy-packs','/api/v1/deployment','/api/v1/entitlements','/api/v1/integrations/conformance','/api/v1/transparency','/api/v1/plans','/api/v1/billing/checkout-session','/api/v1/licenses/activate','/api/v1/licenses/challenge','/api/v1/licenses/token','/api/v1/subscription','/api/v1/billing/portal-session','/api/v1/admin/commerce/overview'];
foreach($required as $route){if(!isset($spec['paths'][$route])){$failures[]='Missing API route: '.$route;}}
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}echo "VeriFact WordPress/API 3.5 contract checks passed.\n";