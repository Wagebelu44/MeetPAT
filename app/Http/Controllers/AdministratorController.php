<?php

namespace MeetPAT\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MeetPAT\Mail\NewUser;
use Illuminate\Support\Str;
use DB;


class AdministratorController extends Controller
{
    // Main Administrator Page
    public function main()
    {

        return view('admin.main');
    }
    // Get all users
    public function users()
    {
        $users = \MeetPAT\User::has('client')->with(['client', 'client_uploads', 'similar_audience_credits', 'client_details'])->get();

        return $users;
    }
    // Get User Count
    public function user_count()
    {
        $user_count = \MeetPAT\User::count();

        return $user_count;
    }
    // create new client
    public function create_user(Request $request)
    {
        $success_message = 'A new user has been added successfully.';

        $validatedData = $request->validate([
            'firstname' => 'required|max:255',
            'lastname' => 'required|max:255',
            'email' => 'required|unique:users|max:255',
            'password' => array(
                                'required',
                                'string',
                                'min:8',
                                'max: 20',
                                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})/',
                                'confirmed'
            )
        ]);

        if($request->give_api_key) {
            $new_user = \MeetPAT\User::create(['name' => $request->firstname . ' ' . $request->lastname,
                                               'email' => $request->email,
                                               'password' => \Hash::make($request->password),
                                               'api_token' => Str::random(60) ]);
        } else {
            $new_user = \MeetPAT\User::create(['name' => $request->firstname . ' ' . $request->lastname,
                                               'email' => $request->email,
                                               'password' => \Hash::make($request->password) ]);            
                                            }

        $new_client = \MeetPAT\MeetpatClient::create(['user_id' => $new_user->id, 'active' => 1]);
        $uploads = \MeetPAT\ClientUploads::create(["user_id" => $new_user->id, "upload_limit" => 10000, "uploads" => 0]);
        
        if($request->send_email)
        {
            $data = [ 'name' => $request->name, 'email' => $request->email, 'password' => $request->password, 'message' => ''];

            \Mail::to($request->email)->send(new NewUser($data));

            $success_message = 'A new user has been added successfully and an email has been sent to the new users email address (' . $request->email. ').';
        }

        return back()->with('success', $success_message);
    }
    // edit client details username, email etc...
    public function edit_user(Request $request)
    {

        $user = \MeetPAT\User::find($request->user_id);
        $response = ["users_id" => $request->user_id, "sent_mail" => "false", "email_valid" => "false", "user_name_valid" => "false", "password_valid" => "false", "password_change" => "false"];

        if (!filter_var($request->user_email, FILTER_VALIDATE_EMAIL) and !\App\User::where('email', $request->email)->first()) {
            $response["email_valid"] = "false";

        } else {
            $response["email_valid"] = "true";
        }

        if($request->user_name) {
            $response["user_name_valid"] = "true";
        } else {
            $response["user_name_valid"] = "false";
        }

        if($request->new_password)
        {
            $response["password_change"] = "true";

            $uppercase = preg_match('@[A-Z]@', $request->new_password);
            $lowercase = preg_match('@[a-z]@', $request->new_password);
            $number    = preg_match('@[0-9]@', $request->new_password);
            $symbol    = preg_match("@[-!$%^&*()\@_+|~=`{}\[\]:\";'<>?,.\/]@", $request->new_password);

            if(!$uppercase || !$lowercase || !$number || !$symbol || strlen($request->new_password) < 8) {

                $response["password_valid"] = "false";

            } else {
                $response["password_change"] = "true";
                $response["password_valid"] = "true";
            }

        } else {
            $response["password_change"] = "false";
        }

        if($user and filter_var($request->user_email, FILTER_VALIDATE_EMAIL) and $request->user_name)
        {
            $user->name = $request->user_name;
            $user->email = $request->user_email;

            if($request->new_password)
            {
                $user->password = \Hash::make($request->new_password);

                if(filter_var($request->send_mail, FILTER_VALIDATE_BOOLEAN))
                {
                    $data = [ 'name' => $request->user_name, 'email' => $request->user_email, 'password' => $request->new_password, 'message' => '' ];

                    \Mail::to($request->user_email)->send(new NewUser($data));  

                    $response["sent_mail"] = "true";
                } else {
                    $response["sent_mail"] = "false";
                }

            } 

            $user->save();
        }

        return $response;

    }

    // delete a client 
    public function delete(Request $request)
    {
        $deleted = false;
        $user = \MeetPAT\User::find($request->user_id);
        $client = $user->client()->first();

        if($user and $client)
        {
            $delete_client = $client->delete();
            $deleted = $user->delete();   
        }

        return response()->json(['email' => $user->email, 'id' => $user->id, 'deleted' => $deleted]);
    }

    public function unique_email(Request $request)
    {
        $user_email = \MeetPAT\User::where('email', $request->email)->first();
        $user = \MeetPAT\User::find($request->user_id);

        if($user_email and $request->email != $user->email)
        {
            $email_used = "true"; 
        } else {
            $email_used = "false"; 
        }

        return response()->json(['email_used' => $email_used]);
    }

    // Set inactive status of a client 

    public function active_change(Request $request)
    {
        $user = \MeetPAT\User::find($request->user_id);
        $status_message = 'An Error has ocured. Please contact us for support.';
        $user_type = 'none';
        $user_was_active = 0;

        if($user and $user->client) {
            $status_message = 'User is a client.';
            $user_type = 'client';

            if($user->client->active)
            {
                $user_was_active = 1;
                $user->client->update(['active' => 0 ]);
            } else {
                $user->client->update(['active' => 1 ]);
            }
            
        } else {
            $status_message = 'User not found';
        }

        return response()->json(['message' => $status_message, 'user_type' => $user_type, 'user_was_active' => $user_was_active]);
    }

    // Views

    // public function users_view()
    // {
    //     $users = \MeetPAT\User::all();

    //     return view('admin.clients.users', ['users' => $users]);
    // }

    public function users_view()
    {
        $users = \MeetPAT\User::with(['client', 'client_uploads', 'similar_audience_credits'])->get();

        return view('admin.clients.users', ['users' => $users]);
    }

    public function create_user_view()
    {
        return view('admin.clients.create_user');
    }

    // route functions for user files to download

    public function display_user_files($user_id)
    {   
        $user = \MeetPAT\User::find($user_id);
        $client_audience_files = \MeetPAT\AudienceFile::where('user_id', $user_id)->get();

        return view('admin.clients.user_files', ['audience_files' => $client_audience_files, 'user' => $user]);
    }

    public function clear_user_uploads(Request $request) {
        $user = \MeetPAT\User::find($request->user_id);

        if($user->client_uploads)
        {
            $user->client_uploads->update(['uploads' => 0]);

        } else {
            return response()->json(["message" => "failed", "user" => $user]);
        }
        
        return response()->json(["message" => "cleared", "user" => $user]);
    }

    public function remove_affiliate(Request $request)
    {
        $user = \MeetPAT\User::find($request->user_id);
        $records_count = 0;

        if($user->client)
        {
            $records = \MeetPAT\BarkerStreetRecord::whereRaw("CAST(".$user->id." as text) = ANY(string_to_array(affiliated_users, ','))");
            if($records->count())
            {
                $records_array = $records->get();
                $first_record = $records->first();
                
                $affiliate_array = explode(",", $first_record->affiliated_users);
                $affiliate_updated = implode(",", array_diff($affiliate_array, array($user->id)));
                
                \MeetPAT\BarkerStreetRecord::whereRaw("CAST(".$user->id." as text) = ANY(string_to_array(affiliated_users, ','))")->update(["affiliated_users" => $affiliate_updated]);

                $records = \MeetPAT\BarkerStreetRecord::whereRaw("CAST(".$user->id." as text) = ANY(string_to_array(affiliated_users, ','))");
                $records_count = $records->count();
            }

        } else {
            return response()->json(["message" => "error"], 500);
        }

        return response()->json(["message" => "success", "records" => $records_count], 200);
    }

    public function delete_file(Request $request)
    {
        $audience_file = \MeetPAT\AudienceFile::find($request->file_id);
        
        if ($audience_file)
        {
            $file_exists = false;
            if(env('APP_ENV') == 'production')
            {
                $file_exists = \Storage::disk('s3')->exists('client/client-records/user_id_' . $request->user_id . '/' . $request->file_id . '.csv');

            } else {
                $file_exists = \Storage::disk('local')->exists('client/client-records/user_id_' . $request->user_id . '/' . $request->file_id . '.csv');

            }

            if($file_exists)
            {
                if(env('APP_ENV') == 'production')
                {
                    $file_deleted = \Storage::disk('s3')->delete('client/client-records/user_id_' . $request->user_id . '/' . $request->file_id . '.csv');
                    $audience_file->delete();
                } else {
                    $file_deleted = \Storage::disk('local')->delete('client/client-records/user_id_' . $request->user_id . '/' . $request->file_id . '.csv');
                    $audience_file->delete();
                }

            } else {
                $audience_file->delete();
            }
    
        } else {
            return response()->json(['message' => 'error', 'text' => 'record not found'], 500);
        }

        return response()->json(['message' => 'success', 'text' => 'record and file has been removed'], 200);
    }

    public function set_upload_limit(Request $request) 
    {

        $user = \MeetPAT\User::find($request->user_id);
        $user_updated = false;

        if($user->client_uploads)
        {
            $user_updated = $user->client_uploads->update(['upload_limit' => $request->new_upload_limit]);
        } else {
            $user_updated = \MeetPAT\ClientUploads::create(['user_id' => $user->id, 'upload_limit' => $request->new_upload_limit]);
        }

        if($user_updated == false)
        {
            return response()->json(["status" => "error", "message" => "An error has occured please contact support."], 500);
        } 

        $client_uploads = \MeetPAT\ClientUploads::where('user_id', $user->id)->first();

        return response()->json(["status" => "success", "message" => "User similar audience credit limit has been updated.", "client_uploads" => $client_uploads], 200);
    }

    public function set_similar_audience_limit(Request $request) 
    {
        $user = \MeetPAT\User::find($request->user_id);
        $user_updated = false;

        if($user->similar_audience_credits)
        {
            $user_updated = $user->similar_audience_credits->update(['credit_limit' => $request->new_credit_limit]);
            
        } else {
            $user_updated = \MeetPAT\SimilarAudienceCredit::create(['user_id' => $user->id, 'credit_limit' => $request->new_credit_limit]);

        }

        if($user_updated == false)
        {
            return response()->json(["status" => "error", "message" => "An error has occured please contact support."], 500);
        } 

        $client_credits = \MeetPAT\SimilarAudienceCredit::where('user_id', $user->id)->first();

        return response()->json(["status" => "success", "message" => "User upload limit has been updated.", "client_uploads" => $client_credits], 200);
    }

    public function enriched_data_tracking() {

        $years = DB::table('enriched_data_trackings')->select(DB::raw("DISTINCT EXTRACT(YEAR FROM created_at) as year"))->orderBy('year')->get();
        $months = DB::table('enriched_data_trackings')->select(DB::raw("DISTINCT EXTRACT(MONTH FROM created_at) as month, to_char(to_timestamp (EXTRACT(Month FROM created_at)::text, 'MM'), 'TMmon') as name"))->orderBy('month')->get();
        
        return view('admin.enriched_data_tracking', ['years' => $years, 'months' => $months]);
    }

    public function get_enriched_data_tracking_day(Request $request) {

        $enriched_data_tracking = DB::table('enriched_data_trackings')->select(DB::raw('COUNT(created_at) records, SUM(sent) as sent, SUM(received) as received, EXTRACT(DAY FROM created_at) as day, EXTRACT(Month FROM created_at) AS month, EXTRACT(YEAR FROM created_at) as year'))->whereRaw('EXTRACT(Month FROM created_at) = ' . $request->month . ' and EXTRACT(YEAR FROM created_at) = ' . $request->year)->groupBy('day', 'created_at')->get();

        return response()->json(array("data" => $enriched_data_tracking, "request" => $request->toArray()));
    }

    public function get_enriched_data_tracking_monthly(Request $request) {

        $enriched_data_tracking = DB::table('enriched_data_trackings')->select(DB::raw('COUNT(created_at) records, SUM(sent) as sent, SUM(received) as received, EXTRACT(Month FROM created_at) AS month, EXTRACT(YEAR FROM created_at) as year'))->whereRaw('EXTRACT(YEAR FROM created_at) = ' . $request->year)->groupBy('month', 'year')->get();

        return response()->json(array("data" => $enriched_data_tracking, "request" => $request->toArray()));
    }

}
