<?php

namespace MeetPAT\Http\Controllers;

use Illuminate\Http\Request;

use Google\Auth\CredentialsLoader;
use Google\Auth\OAuth2;

use Facebook\Facebook;
use FacebookAds\Api;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\CustomAudience;
use FacebookAds\Logger\CurlLogger;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MeetpatClientController extends Controller
{
    // Main Pages

    public function main()
    {
        return view('client.main');
    }

    public function sync_platform()
    {
        $user = \Auth::user();

        $has_facebook_ad_account = $user->facebook_ad_account;
        $has_google_ad_account = $user->google_ad_account;

        return view('client.dashboard.sync', ['has_facebook_ad_account' => $has_facebook_ad_account, 'has_google_ad_account' => $has_google_ad_account]);
    }

    public function upload_clients()
    {
        $user = \Auth::user();

        $has_google_adwords_acc = $user->google_ad_account;
        $has_facebook_ad_acc = $user->facebook_ad_account;

        return view('client.dashboard.upload_clients', ['has_google_adwords_acc' => $has_google_adwords_acc, 'has_facebook_ad_acc' => $has_facebook_ad_acc]);
    }

    // Update synced accounts

    public function sync_facebook()
    {
        $user = \Auth::user();
        $loginUrl = null;

        $fb = new Facebook([
            'app_id' => env('FACEBOOK_APP_ID'),
            'app_secret' => env('FACEBOOK_APP_SECRET'),
          ]);
          
          $helper = $fb->getRedirectLoginHelper();
          
          if (!isset($_SESSION['facebook_access_token'])) {
            $_SESSION['facebook_access_token'] = null;
          }
          
          if (!$_SESSION['facebook_access_token']) {
            $helper = $fb->getRedirectLoginHelper();
            try {
              $_SESSION['facebook_access_token'] = (string) $helper->getAccessToken();
            } catch(FacebookResponseException $e) {
              // When Graph returns an error
              echo 'Graph returned an error: ' . $e->getMessage();
              exit;
            } catch(FacebookSDKException $e) {
              // When validation fails or other local issues
              echo 'Facebook SDK returned an error: ' . $e->getMessage();
              exit;
            }
          }
          
          if ($_SESSION['facebook_access_token']) {

            if($user->ad_account) {
                $user->ad_account->update(['access_token' => $_SESSION['facebook_access_token']]);
                $_SESSION['facebook_access_token'] = null;

                return redirect('/meetpat-client');
            } else {
                $new_ad_account = \MeetPAT\FacebookAdAccount::create(['user_id' => $user->id, 'access_token' => $_SESSION['facebook_access_token']]);
                
                if($new_ad_account) {
                    \Session::flash('success', 'Your facebook account has linked successfully.');
                    // Finally, destroy the session.
                    session_destroy();
                    return redirect('/meetpat-client');

                } else {
                    \Session::flash('error', 'There was a problem linking your account please contact MeetPAT for asssistance.');
                }
            }

          } else {

            $permissions = ['ads_management'];
            $loginUrl = $helper->getReAuthenticationUrl('https://infinite-coast-17182.herokuapp.com/register-facebook-ad-account', $permissions);
            // echo '<a href="' . $loginUrl . '">Log in with Facebook</a>';
          }

        return view('client.dashboard.sync_facebook_acc', ['login_url' => $loginUrl]);
    }


    public function sync_google()
    {
        $PRODUCTS = [
            ['AdWords API', 'https://www.googleapis.com/auth/adwords'],
            ['Ad Manager API', 'https://www.googleapis.com/auth/dfp'],
            ['AdWords API and Ad Manager API', 'https://www.googleapis.com/auth/adwords' . ' '
                . 'https://www.googleapis.com/auth/dfp']
        ];

        // $scopes = $PRODUCTS[2][1] . ' ' . trim(fgets($stdin));

        $oauth2 = new OAuth2(
            [
                'authorizationUri' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'redirectUri' => 'urn:ietf:wg:oauth:2.0:oob',
                'tokenCredentialUri' => CredentialsLoader::TOKEN_CREDENTIAL_URI,
                'clientId' => env('GOOGLE_CLIENT_ID'),
                'clientSecret' => env('GOOGLE_CLIENT_SECRET'),
                'scope' => 'https://www.googleapis.com/auth/adwords' // $scope
            ]
        );

        $auth_uri = $oauth2->buildFullAuthorizationUri();

        return view('client.dashboard.sync_google_acc', ['auth_uri' => $auth_uri]);
    }


    public function authenticate_authorization_code(Request $request)
    {
        $validatedData = $request->validate([
            'adwords_id' => 'required',
            'auth_code' => 'required',
            'user_id' => 'required',
        ]);

        // $PRODUCTS = [
        //     ['AdWords API', 'https://www.googleapis.com/auth/adwords'],
        //     ['Ad Manager API', 'https://www.googleapis.com/auth/dfp'],
        //     ['AdWords API and Ad Manager API', 'https://www.googleapis.com/auth/adwords' . ' '
        //         . 'https://www.googleapis.com/auth/dfp']
        // ];

        // // $scopes = $PRODUCTS[2][1] . ' ' . trim(fgets($stdin));

        // $oauth2 = new OAuth2(
        //     [
        //         'authorizationUri' => 'https://accounts.google.com/o/oauth2/v2/auth',
        //         'redirectUri' => 'urn:ietf:wg:oauth:2.0:oob',
        //         'tokenCredentialUri' => CredentialsLoader::TOKEN_CREDENTIAL_URI,
        //         'clientId' => env('GOOGLE_CLIENT_ID'),
        //         'clientSecret' => env('GOOGLE_CLIENT_SECRET'),
        //         'scope' => 'https://www.googleapis.com/auth/adwords' // $scope
        //     ]
        // );

        $user = \MeetPAT\User::find($request->user_id);
        // $client = $user->client();

        // $code = $request->auth_code;

        // $oauth2->setCode($code);
        // $authToken = $oauth2->fetchAuthToken();

        // if($authToken) {
        //     $has_ad_account = \MeetPAT\GoogleAdwordsAccount::where('user_id', $user->id)->first();
        //     if(!$has_ad_account) {
        //         \MeetPAT\GoogleAdwordsAccount::create(['user_id' => $user->id, 'ad_account_id' => $request->adwords_id, 'access_token' => $authToken['refresh_token'] ]);
        //     } else {
        //         $has_ad_account->update(['ad_account_id' => $request->adwords_id, 'access_token' => $authToken['refresh_token'] ]);
        //     }

        $fileName = uniqid() . '_' . str_replace(" ", "_", $user->id);

        $ini_file = fopen($fileName . ".ini", "w");
        fwrite($ini_file, "developerToken = " . env('GOOGLE_MCC_DEVELOPER_TOKEN') . "\n");
        fwrite($ini_file, "clientCustomerId = " . $request->adwords_id . "\n");
        fwrite($ini_file, "\n");
        fwrite($ini_file, "userAgent = " . "Company Name Placeholder. \n");
        fwrite($ini_file, "\n");
        fwrite($ini_file, "clientId = " . env('GOOGLE_CLIENT_ID') . "\n");
        fwrite($ini_file, "clientSecret = " . env('GOOGLE_CLIENT_SECRET') . "\n");
        fwrite($ini_file, "refreshToekn = " . '1/Bhi8Mk2ErzgUAzM7bk8I0XCAVDJ7Y0ZWEoyPGTssBAQ9oaNM4_kxuic5u9ip2xHM' . "\n");
        fclose($ini_file);

        if(env('APP_ENV') == 'production') {
            $directory_used = \Storage::disk('s3')->makeDirectory('client/ad-words-acc/user_id_' . $user->id);
  
            if($directory_used) {
              $file_uploaded = \Storage::disk('s3')->put('client/ad-words-acc/user_id_' . $user->id .'/' . $fileName . ".ini", $ini_file);
  
            }
          } else {
            $directory_used = \Storage::disk('local')->makeDirectory('client/ad-words-acc/user_id_' . $user->id);
  
            if($directory_used) {
              $file_uploaded = \Storage::disk('local')->put('client/ad-words-acc/user_id_' . $user->id . '/' . $fileName . ".ini", $ini_file);
  
            }
          }

        //     \Session::flash('success', 'Your account has been authorized successfully.');
        // } else {
        //     \Session::flash('error', 'An error occured. Check authorization code or contact MeetPAT for assistance.');
        // }

        return redirect("/meetpat-client");

    }

    public function upload_customers_handle(Request $request)
    {

      $validator = \Validator::make($request->all(), [
        'audience_name' => 'required|unique:audience_files,audience_name,' . $request->user_id,
        'user_id' => 'required',
        'audience_file' => 'required|mimes:csv,txt',
        'file_source_origin' => 'required'
    ]);
    
    if ($validator->fails())
    {
        return response()->json(['errors'=>$validator->errors()]);
    } else {

      $directory_used = null;
      $file_uploaded = null;
      $csv = null;
      
      if($request->file('audience_file')->isValid()) {
        
        $response_text = 'valid file';

        $csv_file = $request->file('audience_file');
        $fileName = uniqid() . '_' . str_replace(" ", "_", $request->audience_name);

        // Testing facebook and google API comment out when ready to upload.

        if(env('APP_ENV') == 'production') {
          $directory_used = \Storage::disk('s3')->makeDirectory('client/custom-audience/user_id_' . $request->user_id);

          if($directory_used) {
            $file_uploaded = \Storage::disk('s3')->put('client/custom-audience/user_id_' . $request->user_id . '/' . $fileName . ".csv", file_get_contents($csv_file));

          }
        } else {
          $directory_used = \Storage::disk('local')->makeDirectory('client/custom-audience/user_id_' . $request->user_id);

          if($directory_used) {
            $file_uploaded = \Storage::disk('local')->put('client/custom-audience/user_id_' . $request->user_id . '/' . $fileName . ".csv", file_get_contents($csv_file));

          }
        }

        $unique_id = uniqid();
        $facebook_job = null;
        $google_job = null;
        $new_jobs = null;

        if($directory_used and $file_uploaded) {
          $audience_file = \MeetPAT\AudienceFile::where([['file_unique_name', '==', $fileName], ['user_id', '==', $request->user_id]])->first();
          if($audience_file) {
            $audience_file->update(['file_unique_name' => $fileName]);
  
          } else {
            $audience_file = \MeetPAT\AudienceFile::create(['user_id' => $request->user_id, 'audience_name' => $request->audience_name, 'file_unique_name' => $fileName, 'file_source_origin' => $request->file_source_origin]);
  
          }

          if($request->facebook_custom_audience) {
            $facebook_job = \MeetPAT\UploadJobQue::create(['user_id' => $request->user_id, 'unique_id' => $unique_id, 'platform' => 'facebook', 'status' => 'pending', 'file_id' => $audience_file->id]);
          }
  
          if($request->google_custom_audience) {
            $google_job = \MeetPAT\UploadJobQue::create(['user_id' => $request->user_id, 'unique_id' => $unique_id, 'platform' => 'google', 'status' => 'pending', 'file_id' => $audience_file->id]);
          }

          function readCSV($csvFile) {
            $file_handle = fopen($csvFile, 'r');
            while (!feof($file_handle) ) {
              $line_of_text[] = fgetcsv($file_handle, 0);
            }
            fclose($file_handle);
            return $line_of_text;
          }
           
          $csv = readCSV($request->file('audience_file')); 
          foreach ( $csv as $c ) {
              $firstColumn = $c[0];
              $secondColumn = $c[1];
              $thirdColumn = $c[2];  
              $fourthColumn = $c[3];
          }

          if($csv) {
            $new_job = \MeetPAT\FacebookJobQue::create(['user_id' => $request->user_id, 'facebook_audience_file_id' => $audience_file->id, 'total_audience' => sizeof($csv) - 1, 'audience_captured' => 0, 'percentage_complete' => 0, 'job_status' => 'ready']);
          
          }

        $new_jobs = \MeetPAT\UploadJobQue::where('unique_id', $unique_id)->get();
  
        }

        } else {
         $response_text = 'in valid file';
        }

        return response()->json($new_jobs);
    
        }

    }

    public function facebook_custom_audience_handler(Request $request)
    {
        $job_que = \MeetPAT\UploadJobQue::where([
            ['unique_id', '=',  $request->unique_id],
            ['platform', '=', 'facebook'],
            ])->first();

        $file_info = \MeetPAT\AudienceFile::find($job_que->file_id);

        if(env('APP_ENV') == 'production') {
            $actual_file = \Storage::disk('s3')->get('client/custom-audience/user_id_' . $file_info->user_id . '/' . $file_info->file_unique_name  . ".csv");
        } else {
            $actual_file = \Storage::disk('local')->get('client/custom-audience/user_id_' . $file_info->user_id . '/' . $file_info->file_unique_name  . ".csv");
        }

        $array = array_map("str_getcsv", explode("\n", $actual_file));
                    
        return response()->json($array);
    }

    public function google_custom_audience_handler(Request $request)
    {
        $job_que = \MeetPAT\UploadJobQue::where([
            ['unique_id', '=',  $request->unique_id],
            ['platform', '=', 'google'],
            ])->first();
        
        $file_info = \MeetPAT\AudienceFile::find($job_que->file_id);

        if(env('APP_ENV') == 'production') {
            $actual_file = \Storage::disk('s3')->get('client/custom-audience/user_id_' . $file_info->user_id . '/' . $file_info->file_unique_name  . ".csv");
        } else {
            $actual_file = \Storage::disk('local')->get('client/custom-audience/user_id_' . $file_info->user_id . '/' . $file_info->file_unique_name  . ".csv");
        }

        $array = array_map("str_getcsv", explode("\n", $actual_file));

        return response()->json($array);
    }

    public function update_facebook()
    {
        return view('client.dashboard.update_facebook_acc', []);
    } 


    public function update_google()
    {
        return view('client.dashboard.update_google_acc', []);
    }
}
