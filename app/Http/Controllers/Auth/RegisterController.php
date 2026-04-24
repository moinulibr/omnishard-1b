<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Http\Resources\UserResource;
use App\Rules\BdPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:100',
            'email'    => 'required|email',
            'phone'    => ['required', new BdPhoneNumber],
            'password' => 'required|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            $user = $this->userService->registerUser($request->all());
            return response()->json([
                'status'  => 'success',
                'message' => 'User registered successfully',
                'data'    => new UserResource($user)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Registration failed',
                'details' => $e->getMessage()
            ], 500);
        }
    }



    //test airline - duffel
    public function searchAirlines(Request $request){
    
        $origin = 'DAC';
        $destination = 'LHR';
        $date = '2026-06-20';

        $cacheKey = "flights_search_{$origin}_{$destination}_{$date}";

        $data = Cache::remember($cacheKey, 180, function () use ($origin, $destination, $date) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('DUFFEL_ACCESS_TOKEN'),
                'Duffel-Version' => 'v2',
                'Content-Type' => 'application/json',
            ])->post('https://api.duffel.com/air/offer_requests', [
                'data' => [
                    'slices' => [
                        [
                            'origin' => $origin,
                            'destination' => $destination,
                            'departure_date' => $date
                        ]
                    ],
                    'passengers' => [['type' => 'adult']],
                    'cabin_class' => 'economy'
                ]
            ]);

            return $response->json();
        });

        if (isset($data['errors']) || (isset($data['data']['offers']) && count($data['data']['offers']) == 0)) {
            Cache::forget($cacheKey);
            return back()->withErrors('API Error: ' . $data['errors'][0]['message']);
        }
        
        return view('airlines.buffel-index', compact("data"));
    }

    public function bookFlight(Request $request)
    {
        $origin = 'DAC';
        $destination = 'LHR';
        $date = '2026-07-20';

        $cacheKey = "flights_search_{$origin}_{$destination}_{$date}";

        $offerId = $request->input('offer_id');
        $totalAmount = $request->input('total_amount');
        $currency = $request->input('currency');
        $passengerId = $request->input('passenger_id'); 

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('DUFFEL_ACCESS_TOKEN'),
            'Duffel-Version' => 'v2',
            'Content-Type' => 'application/json',
        ])->timeout(90)
        ->post('https://api.duffel.com/air/orders', [
            'data' => [
                'type' => 'instant',
                'selected_offers' => [$offerId],
                'passengers' => [
                    [
                        'id' => $passengerId, // it's mendatory
                        'title' => 'mr',      // it's also mendatory (mr, ms, mrs, etc.)
                        'type' => 'adult',
                        'given_name' => 'Moinul',
                        'family_name' => 'Islam',
                        'gender' => 'm',
                        'email' => 'moinul@example.com',
                        'phone_number' => '+8801700000000',
                        'born_on' => '1990-01-01'
                    ]
                ],
                'payments' => [
                    [
                        'type' => 'balance',
                        'amount' => $totalAmount,
                        'currency' => $currency
                    ]
                ]
            ]
        ]);

        $orderData = $response->json();

        if (isset($orderData['errors'])) {
            // dd($orderData['errors']); 
            Cache::forget($cacheKey);
            Log::info($orderData['errors'][0]['message']);
            return back()->withErrors('Booking Failed: ' . $orderData['errors'][0]['message']);
        }

        Cache::forget($cacheKey);

        if (isset($orderData['data'])) {
            session(['booked_order_data' => $orderData['data']]);

            return redirect()->route('flight.booked.succes');
        }

        return view('airlines.order_confirmation', ['order' => $orderData['data']]);
    }

    public function bookedFlightSuccess(){
        $order = session('booked_order_data');
        if (!$order) {
            return redirect('/search-airlines')->withErrors('No booking information found.');
        }
        return view('airlines.order_confirmation', compact('order'));
    }
}
