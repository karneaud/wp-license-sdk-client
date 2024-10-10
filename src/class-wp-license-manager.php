<?php

namespace Karneaud\Licences\Manager;

if(!class_exists('WP_License_Manager')){

    final class WP_License_Manager {
        private $package_version = '1.0.0';
        private $package_slug;
        private $package_id;
        private $type;
        private $api;
        private $error;

        public function __construct(
            string $server,
            string $slug,
            string $type = 'theme',
            string $version = '1.0.0'
        ) {
            $this->package_slug = $slug;
            $this->type = $type;
            $this->package_version = $version
            // Setup license checker to use the SDK client for license authority
            $this->api = WppusClientFactory::create(
                new \Art4\Requests\Psr\HttpClient(['verify' => false, 'verifyname' => false]), 
                $server); // Initialize SDK client
        }

        public function activate_license( string $licence_key, string $package_slug = null, string $type = 'theme' ) {
            if(count(array_filter(func_get_args())) != 3 || [$package_slug, $type] != [$this->package_slug, $this->type]) return false;

            try {
                $request = new PostLicensesActivateRequest(WppusClientFactory::createRequestBody([
                    'licenseKey' => $license_key,
                    'packageSlug' => $this->package_slug,
                    'allowedDomains' => $_SERVER['HTTP_HOST'];
                ]));
                $response = $this->query_server($request, 'postLicensesActivate');
                $response->code = 200;
                $response->message = "License Success: {$response->license_key} Activated!";
            } catch (\Throwable $th) {
                $response = new \Exception(sprintf("License Error: Unable to activate license. %d:  %s", $th->getCode(), $th->getMessage()), $th->getCode());
            }
            
            return $response;
        }

        public function deactivate_license(string $license_key, string $license_sig) {
            
            try {
                $request = new PostLicensesDeactivateRequest(WppusClientFactory::createRequestBody([
                    'licenseKey' => $license_key,
                    'packageSlug' => $this->package_slug,
                    'allowedDomains' => $_SERVER['HTTP_HOST'],
                    'licenseSignature' => $license_sig
                ]));
                $response = $this->query_server($request, 'postLicensesDeactivate');
                $response = $this->query_server($request, 'postLicensesActivate');
            } catch (\Throwable $th) {
               $response = new \Exception(sprintf("License Error: Unable to deactivate license. %d:  %s", $th->getCode(), $th->getMessage()), $th->getCode());
            }
            
            return $response;
        }

        private function query_server(RequestInterface $request, string $func )
        {
            $response = null;
            $response = (object) call_user_func( [$this->update_checker, $func], $request);
        
            return $response;
        }

        private function validate_license(string $license_key, string $license_sig = null)
        {
            $valid = true;
            try {
                $request = new GetLicensesCheckRequest($license, $this->package_slug, $licence_sig );
                $valid = is_object( $this->query_server($request,'getLicensesCheck'));
            } catch (\Throwable $th) {
                $valid = false;
            }

            return  $valid;
        }
       

    }

}

