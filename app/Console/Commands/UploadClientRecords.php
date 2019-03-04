<?php

namespace MeetPAT\Console\Commands;

use Illuminate\Console\Command;

ini_set('memory_limit', '1024M');

class UploadClientRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'records:upload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uploads client records from file.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Methods
        function check_value($value)
        {
            if($value == '')
            {
                return 'Unknown';
            } else {
                return $value;
            }
        }

        // validate mobile numbers

        function validate_mobile_number($number) {

            if(strlen($number) == 11 and $number[0] == '2' and $number[1] == '7') {
                
                return $number;
            } else {

                return 'Unkown';

            }

        }

        // get age group ( AgeGroup )from ex. 03. Thirties 

        function get_age_group($age_group) {

            switch ($age_group) {
                case "02 Twenties":
                    return "02";
                    break;
                case "03. Thirties":
                    return "03";
                    break;
                case "04. Fourties":
                    return "04";
                    break;
                case "05. Fifties":
                    return "05";
                    break;  
                 case "06. Sixties":
                    return "05";
                    break;  
                case "07. Senventies":
                    return "05";
                    break;  
                case "08. Eighty +":
                    return "08";
                    break;    
                default:
                    return 'Unknown';                                                   
            }


        }

        // get Gender F , M
        function get_gender($gender)
        {
            switch ($gender) {
                case "Male":
                    return "M";
                    break;
                case "Female":
                    return "F";
                    break;
                default:
                    return 'Unknown';
            }
        }

        // get PopulationGroup

        function get_population_group($p_group) 
        {
            switch ($p_group) {
                case "Black":
                    return "B";
                    break;
                case "White":
                    return "W";
                    break;
                case "Coloured":
                    return "C";
                    break;
                case "Asian":
                    return "A";
                case "Unkown":
                    return "Unkown";
                    break;
                default:
                    return "Unkown";
            }
        }

        // find IncomeBucket

        function find_income_bucket($income)
        {
            if($income < 2501) {
                return "R0 - R2 500";
            } else if ($income >= 2500 and $income < 5001) {
                return "R2 500 - R5 000";
            } else if ($income >= 5000 and  $income < 10001) {
                return "R5 000 - R10 000";
            } else if ($income >= 10000 and $income < 20001) {
                return "R10 000 - R20 000";
            } else if ($income >= 20000 and $income < 30001) {
                return "R20 000 - R30 000";
            } else if ($income >= 30000 and $income < 40001) {
                return "R30 000 - R40 000";
            } else {
                return "R40 000 +";
            }
        }

        // find CreditRiskCategory
        function find_category($category)
        {
            return str_replace(" ", "_", trim(explode('.', $category)[1]));
        }

        // format province
        function format_province($province)
        {
            switch ($province) {
                case "Gauteng":
                    return "G";
                    break;
                case "Eastern Cape":
                    return "EC";
                    break;
                case "Western Cape":
                    return "WC";
                    break;
                case "Northern Cape":
                    return "NC";
                case "Limpopo":
                    return "L";
                    break;
                case "Free State":
                    return "FS";
                    break;
                case "Mpumalanga":
                    return "M";
                    break;
                case "Kwazulu-Natal":
                    return "KN";
                    break;
                case "North West":
                    return "NW";
                    break;
                default:
                    return 'Unknown';
            }
        }

        $records_job_que = \MeetPAT\RecordsJobQue::where('status', 'pending')->get();
        $records_job_que_running = \MeetPAT\RecordsJobQue::where('status', 'running')->count();

        if($records_job_que_running == 0) {
            foreach($records_job_que as $job) {

                if($job->status == 'pending') {
                    $job->update(['status' => 'running']);

                    $audience_file = \MeetPAT\AudienceFile::find($job->audience_file_id);
                    $file_exists = '';

                    if(env('APP_ENV') == 'production') {
    
                        $file_exists = \Storage::disk('s3')->exists('client/client-records/user_id_' . $audience_file->user_id . '/' . $audience_file->file_unique_name . ".csv");
                    } else {
                        $file_exists = \Storage::disk('local')->exists('client/client-records/user_id_' . $audience_file->user_id . '/' . $audience_file->file_unique_name . ".csv");
    
                    }

                    if($file_exists) {
                        if(env('APP_ENV') == 'production') {
                            $actual_file = \Storage::disk('s3')->get('client/client-records/user_id_' . $audience_file->user_id . '/' . $audience_file->file_unique_name  . ".csv");
                        } else {
                            $actual_file = \Storage::disk('local')->get('client/client-records/user_id_' . $audience_file->user_id . '/' . $audience_file->file_unique_name  . ".csv");
                        }
    
                        $array = array_map("str_getcsv", explode("\n", $actual_file));
                        unset($array[0]);
                        unset($array[sizeof($array)]);
                        
                        foreach($array as $row) {      
                              
                            $data = [
                                'Idn' => check_value($row[0]),
                                'FirstName' => check_value($row[1]),
                                'Surname' => check_value($row[2]),
                                'MobilePhone1' => check_value(validate_mobile_number($row[3])),
                                'MobilePhone2' => check_value(validate_mobile_number($row[4])),
                                'MobilePhone3' => check_value(validate_mobile_number($row[5])),
                                'WorkPhone1' => check_value(validate_mobile_number($row[6])),
                                'WorkPhone2' => check_value(validate_mobile_number($row[7])),
                                'WorkPhone3' => check_value(validate_mobile_number($row[8])),
                                'HomePhone1' => check_value(validate_mobile_number($row[9])),
                                'HomePhone2' => check_value(validate_mobile_number($row[10])),
                                'HomePhone3' => check_value(validate_mobile_number($row[11])),
                                'AgeGroup' => check_value(get_age_group($row[12])),
                                'Gender' => check_value(get_gender($row[13])),
                                'PopulationGroup' => check_value(get_population_group($row[14])),
                                'DeceasedStatus' => check_value($row[15]),
                                'MaritalStatus' => check_value($row[16]),
                                'DirectorshipStatus' => check_value($row[17]),
                                'HomeOwnerShipStatus' => check_value($row[18]),
                                'income' => check_value($row[19]),
                                'incomeBucket' => check_value(find_income_bucket($row[20])),
                                'LSMGroup' => check_value($row[21]),
                                'CreditRiskCategory' => check_value(find_category($row[22])),
                                'ContactCategory' => check_value(find_category($row[23])),
                                'HasMobilePhone' => check_value($row[24]),
                                'HasResidentialAddress' => check_value($row[25]),
                                'Province' => check_value(format_province($row[26])),
                                'GreaterArea' => check_value($row[27]),
                                'Area' => check_value($row[28]),
                                'ResidentialAddress1Line1' => check_value($row[29]),
                                'ResidentialAddress1Line2' => check_value($row[30]),
                                'ResidentialAddress1Line3' => check_value($row[31]),
                                'ResidentialAddress2Line4' => check_value($row[32]),
                                'ResidentialAddress2PostalCode' => check_value($row[33]),
                                'PostalAddress1Line1' => check_value($row[34]),
                                'PostalAddress1Line2' => check_value($row[35]),
                                'PostalAddress1Line3' => check_value($row[36]),
                                'PostalAddress1Line4' => check_value($row[37]),
                                'PostalAddress1PostalCode' => check_value($row[38]),
                                'email' => check_value($row[39]),
                                'affiliated_users' => $audience_file->user_id . ','
                            ];
                
                            $insert_data[] = $data;
                        
                    }
                
                    $insert_data = collect($insert_data);
                    $chunks = $insert_data->chunk(1000);
                
                    foreach($chunks as $chunk) {
                        \MeetPAT\BarkerStreetRecord::insert($chunk->toArray());
                        $job->increment('records_completed', sizeof($chunk));

                        // $this->info($job->records_completed);
                    }
    
                    }
                }
                $job->update(['status' => 'done']);

            }

        } else {
            $this->info('Jobs already running.');
        }

        


    }
}
