# Tequila.kiwi API

+ go to [tequila.kiwi.com](https://tequila.kiwi.com/) and create an account for your business

+ create a __solution__ to get `API Key` 
<br>
choose Online travel agency - API integration

+ in __settlement options (payment methods)__, you can choose either `Credit Card` or `Deposit (Bank Transfer)` <br>
Important thing to note is that __this is the payment between your business and the Tequila.kiwi__

+ in your __.env__ file of your laravel project, add these variables

```dotenv
TEQUILA_API_KEY=your-tequila-api-key
TEQUILA_BASE_URL="https://api.tequila.kiwi.com"
TEQUILA_PAYMENT=""
TEQUILA_PHONE=user-phone
TEQUILA_EMAIL=your-business-email
```

+ for `TEQUILA_PAYMENT`, just leave an empty string for __Deposit/Bank Transfer__ settlement option, for __Credit Card__ option, type "zooz", like this `TEQUILA_PAYMENT="zooz"`

+ even though you can get these .env variables in your laravel project like this 
```php
env('TEQUILA_API_KEY')
```
i suggest you to create a __config file__ for tequila, name that file __tequila.php__, and set configs in there
```php
'api_key' => env('TEQUILA_API_KEY', 'you can also set default value here')
```
so you can use config values like this
```php
config('tequila.api_key')
```

+ copy [TequilaService.php](TequilaService.php) file into your project, and depending on the place you put, change its __namespace__. <br>

+ to use the methods inside this file, maybe it's the best to do __Dependency Injection__ in your controller 

```php
use App\Services\TequilaService; // change according on the namespace of TequilaService.php file

public function __construct(TequilaService $tequila_service)
{
    // 
    $this->tequila_service = $tequila_service;
}
```

+ install `guzzlehttp/guzzle` package in your laravel project

> Now we are ready to make API calls to Tequila.kiwi 

## Locations API
since we are searching flights *from one city to another*, we need __city codes__
<br>
we can search flights *from one airpot to another* (with __airport IATA codes__) but most of the time we don't care that much about which airpots, right? 
<br>
we just want to land in that city
<br>
use this method `get_city_code` to get __city code__ 

```php
// example usage
$result = $this->tequila_service->get_city_code('tokyo');
```

example response
```json
"locations": [
    {
        "id": "tokyo_jp",
        "active": true,
        "name": "Tokyo",
        "slug": "tokyo-japan",
        "slug_en": "tokyo-japan",
        "code": "TYO",                  // this code here
        "alternative_names": [
            "東京"
        ],
    },
]
```

## Search API
now we got the __city code__, we can start searching flights by using this method `search_flights`
<br>
let's say, we are searching flights from Yangon to Tokyo on March 21st

```php
// example usage 
$result = $this->tequila_service->search_flights('RGN', 'TYO', '2024-03-21');
```

example response
```json
{
    "search_id": "4e159077-f9d6-fa6d-0391-803b4b39a09f",
    "currency": "EUR",
    "fx_rate": 1,
    "data": [
        {},             // flight itineraries here
        {}
    ],
    "_results": 55
}
```

__3 important things__ from the response which are needed to proceed to next step in booking the itinerary
+ search_id
+ booking_token
+ bags_price

```json
// search_id example
"search_id": "4e159077-f9d6-fa6d-0391-803b4b39a09f"

// booking_token example
"booking_token": "G1nkblAQSd6ZYYwAhgwZ7a0JKo5Ok89SuOxxNp_TcuZz3naOFtYbat3_l7MDJOrTSwTpOJD_F5cHyV2Crkg7JGpQ5j8WFAfVxS-laqiF7CLbjiUGY9-Pd6mkGp8Pu90XZ9F_5hOmfNGx01IrQ35voNS7y8m2gMCN5z6XtMqXBx_HyxyBOxGrYTha-K5piaZ4Q9qOU3K_2p6coHtQl_0OiSJX-cYQkakllU8_RqyDNi9e6yPF4Oh6i-PrgsQMaWJbjynIaH-0sCNV5Mz5CTuKIee_7fAEsVAnY1fJJPXQOdv3xkZWQyomzuAqtBHksZ4PdrQYFuSaksqwSeAFdeMd8SuCp3wQfv8WlOq-pwQsIvn_AMw5rV4Ve-2RdwrfRPRQGeGCmfI0DzwmXauSBJ9i2_01lrKfFPKqSp-gQvIbO_FhCtQYGOrH07z80BPz0nSwC9PmdYpsIWiqxo_r9eAs9OxIcYmV8L01lK6nrlSi3zSC9YHZabC2vnyUAUrXO6YKReN4ChESuIBdZMvKCzJn8FxrmTqhsmWC5VOmrGaP8HL70x6eSB0QbBvaLZc8zdBMQXWvMmHkrwmi_zJohKjU_DDn_MoQCZoQMnSNu9HT2iYARJ_xThCaV6UASXw18pFUT"

// bags_price example
// example 1
"bags_price": {
    "1": 114.165,
    "2": 222.22500000000002
},

// example 2
"bags_price": {
    "1": 66.99
},
```

you can find the `booking_token` and `bags_price` in each flight itinerary

i will explain about `bags_price` in next step (Check Flights)


## Booking API: Check Flights
> This is the first step of booking

```php
public function check_flights(
    string $session_id,
    string $booking_token,
    int $bnum,          // the number of bags for the entire booking
    int $adults,
    int $children,
    int $infants)
```

put `session_id (search_id)` and `booking_token` that you got from *previous step*
<br>
you will wonder, what do i put in `$bnum`?
<br>
as i said in the previous step, i'll explain about `bags_price` here

use this method `get_bnum(array $bags_price)` to get __bnum__ from __bags_price__

one thing to note here is that, this method takes an array, so change it to array
```php
// example usage
$bnum = $this->tequila_service->get_bnum((array)$bags_price)
```

now, we get __$bnum__, we can use the method `check_flights`

from the `check_flights` response, we will check if the itinerary is available by checking these 3 properties if they are like below, and then we can proceed to the next step (Save Booking) 

```json
"flights_invalid": false,
"price_change": false,
"flights_checked": true,
```
+ If the value of `flights_invalid` returns `true`, the itinerary is __not available to book__ as it is either _canceled by the airline_ or _sold out_.
<br>
The `flights_invalid` property needs to return the value `false` to be able to continue with booking the itinerary.

+ If `price_change` returns `true`, look for the __new price__ in the response under `total`.

another important property form `check_flights` response is `baggage` property
<br>
i'll explain about its usage in the next step

```php
// baggage example
"baggage" => [
    "definitions" => [
        'hold_bag' => [
            // hold bag definitions
        ],
        'hand_bag' => [
            // hand bag definitions
        ]
    ],
    "combinations" => [
        "hold_bag" => [
            // hold bag combinations
        ]
        "hand_bag" => [
            // hand bag combinations
        ]
    ],
    "notices" => [
        // notices
    ]         
]
```

## Booking API: Save Booking
this step will create a __booking order__ in Tequila.kiwi
<br>
subsequently, it freezes the respective amount of funds from the __settlement option /payment method__ you are using (deposit/bank transfer or credit card). 
<br>
Tequila.kiwi will wait the transaction from your side.
<br>
check these steps before using `save_booking` method 

#### Step 1. Make a passenger array
If you want an example, call `make_test_passenger` method to get a test passenger info

```php
$passengers = [];
$passengers[] = $this->tequila_service->make_test_passenger();
```

#### Step 2. Select hand_bag and hold_bag combinations
In the previous step, I told about the `baggage` property
<br>
in that `baggage` array, there is `definitions` and `combinations`, right?

check __baggage example__ in the above for reference if you can't find it in your __check_flights__ response

by implementing frontend, you can let the user choose their desired __hand_bag and hold_bag combinations__

`definitions` is the info about those `combinations`

call this method to make data that is required for booking

```php
public function make_save_booking_data(
        string $session_id,
        string $booking_token,
        array $passengers,
        array $hold_bag_combination,            // hold bag combination the user chose
        array $hand_bag_combination)            // hand bang combination the user chose
```

#### Step 3. Save booking
Now you can use this method `save_booking` to book at Tequila.kiwi

after you successfully booked, you can find these properties in the response
+ booking_id
+ transaction_id
+ payu_token (if your payment method is Credit Card)

## Booking API: Payment
if the __settlement options/ payment method__ between your business and Tequila.kiwi is `Bank Transfer (Deposit)`, use this method to make payment

```php
public function confirm_payment(string $booking_id, string $transaction_id)
```

## Booking API: Payment (Zooz)
if your __settlement options/ payment method__ is `Credit Card`, you need to do 2 steps

#### Step 1. Tokenize
call this method to get tokenize data

```php
public function tokenize(
        string $booking_id, 
        string $payu_token, 
        bool $sandbox=false)
```

example response
```json
{
    "status": "success",
    "token": "a07e58f3-ce5f-4068-817b-09fd8cb0b516",
    "encrypted_cvv": "da1ebb37-64c6-44a5-a193-fcc8a44712ad",
    "bin_number": "411111",
    "last_4_digits": "1111",
    "holder_name": "TEST APPROVE",
    "expiration": "2026-01-01",
    "vendor": "VISA",
    "issuer": null,
    "country_code": "PL",
    "level": "CLASSIC",
    "type": "DEBIT",
    "pass_luhn_validation": true,
    "risk_assessment": {
        "correlationId": "2024-03-08T160237982-87a95a7c-v3",
        "version": "3DS disabled",
        "status": "success"
    }
}
```

#### Step 2. Confirm zooz payment
after you got __tokenize data__, you can make payment to Tequila.kiwi by using this method `confirm_payment_zooz`

```php
// example
$save_booking_data = $this->tequila_service->save_booking($data) // refer to Save Booking

$tokenize_data = $this->tequila_service->tokenize($save_booking_data['booking_id'], $save_booking_data['payu_token']);

$result = $this->tequila_service->confirm_payment_zooz($tokenize_data, $save_booking_data['booking_id'], $save_booking_data['payu_token']);
```