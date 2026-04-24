<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Search Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-5 md:p-10">
    <div class="max-w-4xl mx-auto">

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Available Flights</h1>
                <p class="text-sm text-gray-500 font-medium mt-1">Showing the best offers for your trip</p>
            </div>

            <div class="mt-4 md:mt-0 flex flex-wrap items-center gap-3">
                <div class="flex items-center bg-blue-50 px-4 py-2 rounded-full border border-blue-100">
                    <span class="text-blue-700 font-bold text-sm uppercase">
                        {{ $data['data']['offers'][0]['slices'][0]['origin']['iata_code'] ?? 'N/A' }}
                    </span>
                    <svg class="w-4 h-4 mx-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                    <span class="text-blue-700 font-bold text-sm uppercase">
                        {{ $data['data']['offers'][0]['slices'][0]['destination']['iata_code'] ?? 'N/A' }}
                    </span>
                </div>

                <div class="flex items-center bg-gray-50 px-4 py-2 rounded-full border border-gray-200">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span class="text-gray-700 font-semibold text-sm">
                        @php
                            $departureDate = $data['data']['offers'][0]['slices'][0]['segments'][0]['departing_at'] ?? null;
                        @endphp
                        {{ $departureDate ? \Carbon\Carbon::parse($departureDate)->format('d M, Y') : 'Date N/A' }}
                    </span>
                </div>

                <div class="flex items-center bg-gray-50 px-4 py-2 rounded-full border border-gray-200">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span class="text-gray-700 font-semibold text-sm">
                        {{ count($data['data']['offers'][0]['passengers'] ?? []) }} Passenger(s)
                    </span>
                </div>
            </div>
        </div>
        @if ($errors->any())
            <div style="color: red;">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                   
                </ul>
            </div>
            <br/>
        @endif

        @foreach($data['data']['offers'] as $offer)
        <div class="bg-white rounded-lg shadow-md mb-6 overflow-hidden border border-gray-200">
            <div class="bg-gray-50 px-6 py-3 border-b flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <img src="{{ $offer['owner']['logo_symbol_url'] }}" alt="Logo" class="w-10 h-10 object-contain">
                    <span class="font-bold text-lg text-blue-900">{{ $offer['owner']['name'] }}</span>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-extrabold text-blue-600">{{ $offer['total_amount'] }}</span>
                    <span class="text-sm font-semibold text-gray-500">{{ $offer['total_currency'] }}</span>
                </div>
            </div>

            <div class="p-6">
                @foreach($offer['slices'] as $slice)
                <div class="flex flex-col md:flex-row justify-between items-center mb-4">
                    <div class="text-center md:text-left">
                        <div class="text-2xl font-bold uppercase">{{ $slice['origin']['iata_code'] }}</div>
                        <div class="text-sm text-gray-500">{{ $slice['origin']['city_name'] }}</div>
                    </div>

                    <div class="flex flex-col items-center flex-grow px-4">
                        <span class="text-xs text-gray-400 font-medium mb-1">{{ str_replace(['PT', 'H', 'M'], ['', 'h ', 'm'], $slice['duration']) }}</span>
                        <div class="w-full h-px bg-gray-300 relative">
                            <div class="absolute -top-1.5 left-1/2 -translate-x-1/2 text-gray-400">✈</div>
                        </div>
                        <span class="text-xs text-green-600 mt-1 font-bold">{{ count($slice['segments']) > 1 ? (count($slice['segments']) - 1) . ' Stop(s)' : 'Direct' }}</span>
                    </div>

                    <div class="text-center md:text-right">
                        <div class="text-2xl font-bold uppercase">{{ $slice['destination']['iata_code'] }}</div>
                        <div class="text-sm text-gray-500">{{ $slice['destination']['city_name'] }}</div>
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    @foreach($slice['segments'] as $index => $segment)
                        <div class="bg-blue-50 p-4 rounded-xl border border-blue-100 flex justify-between items-center relative">
                            <div class="flex items-center space-x-4">
                                <div class="bg-white p-2 rounded-lg shadow-sm">
                                    <span class="text-xs font-bold text-blue-600 block leading-tight">FLIGHT</span>
                                    <span class="text-sm font-black text-gray-800">{{ $segment['marketing_carrier_flight_number'] }}</span>
                                </div>
                                
                                <div>
                                    <p class="text-sm font-bold text-gray-800 uppercase">
                                        {{ $segment['origin']['iata_code'] }} 
                                        <span class="text-gray-400 mx-1">→</span> 
                                        {{ $segment['destination']['iata_code'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 italic">
                                        Departure: {{ \Carbon\Carbon::parse($segment['departing_at'])->format('d M, h:i A') }}
                                    </p>
                                </div>
                            </div>

                            @if(!$loop->last)
                                <div class="absolute -bottom-3 left-10 z-10 bg-orange-100 text-orange-600 px-3 py-0.5 rounded-full text-[10px] font-bold border border-orange-200">
                                    Connection / Change Planes
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @endforeach

                <div class="mt-6 flex justify-end">
                    <form action="{{ route('flight.book') }}" method="POST">
                        @csrf
                        <input type="hidden" name="offer_id" value="{{ $offer['id'] }}">
                        <input type="hidden" name="total_amount" value="{{ $offer['total_amount'] }}">
                        <input type="hidden" name="currency" value="{{ $offer['total_currency'] }}">
                        
                        <input type="hidden" name="passenger_id" value="{{ $offer['passengers'][0]['id'] }}">

                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-8 rounded-full transition duration-300">
                            Book This Flight
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</body>
</html>