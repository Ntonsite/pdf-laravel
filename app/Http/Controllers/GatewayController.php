<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PDFController;
use App\Models\Customer;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class GatewayController extends Controller
{
    public function __construct()
    {
        $this->bankingApi  = 'https://192.168.0.124/API/';
        $this->client      = new Client(['verify' => false]);
        $this->time        = new Carbon();
        $this->generatePDF = new PDFController(); 
    }

    public function index(Customer $customer)
    {
        $customers = $customer::select('Client_Name','cEmail','Account_number','Balance')->get();

        foreach ($customers as $row)
        {
            $name         = $row['Client_Name'];
            $email        = $row['cEmail'];
            $account      = $row['Account_number'];
            $balance      = $row['Balance'];
            
            if($transactions = $this->transactions($account))
            {
               $data = array(
                'name'        => $name,
                'email'       => $email,
                'account'     => $account,
                'balance'     => $balance,
                'transactions'=> $transactions
                );
            }
            return $this->generatePDF->index($data); 
        }
    }


    public function transactions($accountID)
    {
        $token = $this->apiAuth();
        $currentDateTime = $this->time::now();
        //$dateTo = $currentDateTime->toDateTimeString();
        $dateTo = '2021-10-06';
        //$dateFrom = $currentDateTime->subMinute(30)->toDateTimeString();
        $dateFrom = '2021-06-17';
    
        try {
            $response = $this->client->request('GET', $this->bankingApi ."Accounts/{$accountID}/Transactions",
            [   
                'query'   => [
                'toDate' => $dateTo, 
                'fromDate' => $dateFrom
            ],
                'headers' => [
                    'Authorization' => 'Bearer ' .$token,
                    'Accept'        => 'application/json',
                ],
            ]);

            return $response->getBody()->getContents();
        } 
        catch (Exception $e) 
        {
           throw new Exception($e->getResponse()->getBody()->getContents()); 
        }
    }

    public function apiAuth()
    {
        $response = $this->client->request('GET', $this->bankingApi . 'Auth/login', 
        [
            'form_params' => [
            'username' => Config::get('app.api_user'),
            'password' => Config::get('app.api_password'),
            ],
            'headers'=> [
                'Authorization' => 'Basic VGlzc0FQSV90ZXN0OkFtYnQkNA==',
                'Accept'        => 'application/json',
            ],
        ]);

        return json_decode($response->getBody())->token;
    }
}
