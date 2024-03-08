<?php

namespace App\Services; // change namespace according to your project

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class TequilaService
{
    protected $locale = 'en-US';
    protected $currency = 'EUR';

    public function parse_response(Response $response, bool $associative = true)
    {
        $stream = $response->getBody();
        $obj = json_decode($stream, $associative);
        return $obj;
    }

    public function parse_error_response(ClientException $e, bool $associative = true)
    {
        $stream = $e->getResponse()->getBody();
        $obj = json_decode($stream, $associative);
        return $obj;
    }

    public function http($content_encoding = 'gzip')
    {
        $headers = [
            'apikey' => config('tequila.api_key'),
        ];
        if ($content_encoding === 'gzip') {
            $headers['Accept'] = '*/*';
            $headers['Content-Encoding'] = 'gzip';
        }

        $http = new Client([
            'base_uri' => config('tequila.base_url'),
            'headers' => $headers
        ]);
        return $http;
    }

    // Location API (GET)
    public function get_city_code(string $city_name)
    {
        $url = '/locations/query';
        $query = [
            'locale' => $this->locale,
            'term' => $city_name,
            'location_types' => 'city',
        ];
        $query = http_build_query($query);
        $url .= '?' . $query;

        $response =  $this->http()->get($url);
        return $this->parse_response($response);
    }

    public function cabin($class)
    {
        // M (economy), W (economy premium), C (business), F (first class)
        $class = strtolower($class);
        switch ($class) {
            case 'first':
                return 'F';
            case 'business':
                return 'C';
            case 'economy premium':
                return 'W';
            case 'premium economy':
                return 'W';
            default:
                return 'M';
        }
    }

    // Search API (GET)
    public function search_flights(
        string $from,
        string $to,
        string $depart_date,
        string $return_date = null,
        string $class = 'economy',
        int $adults = 1,
        int $children = 0,
        int $infants = 0,
        int $limit = 50
    ) {
        $depart_date = Carbon::parse($depart_date)->format('d/m/Y');

        $url = '/v2/search';
        $query = [
            'fly_from' => 'city:' . $from,
            'fly_to' => 'city:' . $to,
            'date_from' => $depart_date, // (dd/mm/yyyy)
            'date_to' => $depart_date, // (dd/mm/yyyy)
            'selected_cabins' => $this->cabin($class),
            'curr' => $this->currency,

            // The sum of adults, children and infants cannot be greater than 9.
            'adults' => $adults, // default is 1
            'children' => $children, // default is 0.
            'infants' => $infants, // default is 0
            'limit' => $limit, // default 200
            'vehicle_type' => 'aircraft' // aircraft (default), bus, train
            // 'max_stopovers' => 0, // max number of stopovers per itinerary.  Use 'max_stopovers=0' for direct flights only.
        ];
        if ($return_date) {
            $return_date = Carbon::parse($return_date)->format('d/m/Y');
            $query['return_from'] = $return_date;
            $query['return_to'] = $return_date;
        }
        $query = http_build_query($query);
        $url .= '?' . $query;
        $response = $this->http()->get($url);
        return $this->parse_response($response);
    }

    public function get_bnum(array $bags_price)
    {
        ksort($bags_price, SORT_NUMERIC);
        return array_key_last($bags_price);
    }

    // Booking API: Check Flights (GET)
    public function check_flights(
        string $session_id,
        string $booking_token,
        int $bnum,          // the number of bags for the entire booking
        int $adults,
        int $children,
        int $infants
    ) {
        $url = '/v2/booking/check_flights';
        $query = [
            'session_id' => $session_id,
            'booking_token' => $booking_token,
            'bnum' => $bnum,
            'adults' => $adults,
            'children' => $children,
            'infants' => $infants,
            'curr' => $this->currency,
        ];
        $query = http_build_query($query);
        $url .= '?' . $query;
        $response = $this->http()->get($url);
        return $this->parse_response($response);
    }

    public function make_test_passenger()
    {
        return [
            'name' => 'test',
            'surname' => 'test',
            'phone' => '+959111222333', // customer's phone
            'email' => config('tequila.email'),

            'cardno' => 'AA445566', // passport
            'expiration' => '2029-01-01', // YYYY-MM-DD format
            'birthday' => '1994-10-31', // YYYY-MM-DD format
            'nationality' => 'MM',      // ISO 3166-1 alpha-2 country code
            'title' => Arr::random(['Mr', 'Ms']),            // either Mr or Ms
            'category' => 'adult',              // adult, child, infant
        ];
    }

    function filter_by_age_groups(array $passenger_groups, array $passengers)
    {
        $keys = [];
        foreach ($passenger_groups as $group) {
            foreach ($passengers as $k => $passenger) {
                if ($group === $passenger['category']) {
                    $keys[] = $k;
                }
            }
        }
        return $keys;
    }

    public function make_save_booking_data(
        string $session_id,
        string $booking_token,
        array $passengers,
        array $hold_bag_combination,
        array $hand_bag_combination
    ) {
        $hold_bag_keys = $this->filter_by_age_groups($hold_bag_combination['conditions']['passenger_groups'], $passengers);
        $hand_bag_keys = $this->filter_by_age_groups($hand_bag_combination['conditions']['passenger_groups'], $passengers);

        $data_array = [
            'health_declaration_checked' => true,
            'passengers' => $passengers,
            'locale' => $this->locale,
            'session_id' => $session_id,
            'booking_token' => $booking_token,
            'baggage' => [
                [
                    'combination' => $hold_bag_combination,
                    'passengers' => $hold_bag_keys
                ],
                [
                    'combination' => $hand_bag_combination,
                    'passengers' => $hand_bag_keys
                ],
            ],
        ];
        if (config('tequila.payment') === 'zooz') {
            $data_array['payment_gateway'] = 'payu';
            $data_array['currency '] = $this->currency;
        }
        return $data_array;
    }

    // Booking API: Save Booking (POST)
    public function save_booking(array $data)
    {
        $url = '/v2/booking/save_booking';
        $response = $this->http()->post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $data
        ]);
        return $this->parse_response($response);
    }


    // Booking API: Payment
    public function confirm_payment(string $booking_id, string $transaction_id)
    {
        $url = '/v2/booking/confirm_payment';
        $response = $this->http()->post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'booking_id' => $booking_id,
                'transaction_id' => $transaction_id
            ]
        ]);
        return $this->parse_response($response);
    }

    public function tokenize(
        string $booking_id,
        string $payu_token,
        bool $sandbox=false)
    {
        if ($sandbox) {
            $url = config('tequila.tokenize_url_sandbox');
            $card = config('tequila.card_sandbox');
        } else {
            $url = config('tequila.tokenize_url');
            $card = config('tequila.card');
        }
        $data = [
            'card' => $card,
            'payment' => [
                'order_id' => $booking_id,
                'token' => $payu_token,
                'gate' => 'pos',
                'email' => config('tequila.email')
            ]
        ];

        $response =  $this->http(null)->post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $data
        ]);
        return $this->parse_response($response);
    }

    // Booking API: Payment (Zooz)
    public function confirm_payment_zooz(
        array $tokenize_data,
        string $booking_id,
        string $payu_token,
        bool $sandbox=false)
    {
        $url = '/v2/booking/confirm_payment_zooz';

        $data = [
            'payment_details' => array_only($tokenize_data, [
                'status',
                'token',
                'encrypted_cvv',
                'bin_number',
                'last_4_digits',
                'holder_name',
                'expiration',
                'vendor',
                'issuer',
                'country_code',
                'level',
                'type',
                'pass_luhn_validation',
            ]),
            'booking_id' => $booking_id,
            'order_id' => $booking_id,
            'paymentToken' => $payu_token,
            'paymentMethodToken' => $tokenize_data['token'],
            'sandbox' => $sandbox,
            'language' => 'en-GB'
        ];

        $response = $this->http()->post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $data
        ]);
        return $this->parse_response($response);
    }
}
