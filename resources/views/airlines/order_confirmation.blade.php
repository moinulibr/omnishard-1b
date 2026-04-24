<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - {{ $order['booking_reference'] }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .ticket-visual {
            background: linear-gradient(135-deg, #1e40af 0%, #3b82f6 100%);
        }
        .dotted-line {
            border-top: 2-px dashed #e5e7eb;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="max-w-3xl mx-auto py-12 px-4">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-100 rounded-full mb-4">
                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900">Booking Confirmed!</h1>
            <p class="text-gray-600 mt-2">Your flight has been successfully booked. Bon voyage!</p>
        </div>

        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden border border-gray-100">
            <div class="ticket-visual px-8 py-6 text-white flex justify-between items-center">
                <div>
                    <p class="text-blue-100 text-sm uppercase tracking-wider font-semibold">Booking Reference</p>
                    <h2 class="text-4xl font-mono font-bold tracking-tighter">{{ $order['booking_reference'] }}</h2>
                </div>
                <div class="text-right">
                    <p class="text-blue-100 text-sm uppercase tracking-wider font-semibold">Status</p>
                    <span class="bg-green-400 text-green-900 px-3 py-1 rounded-full text-xs font-bold uppercase">Confirmed</span>
                </div>
            </div>

            <div class="p-8">
                <div class="flex justify-between text-sm text-gray-500 mb-8 pb-4 border-b">
                    <span>Order ID: <span class="font-medium text-gray-800">{{ $order['id'] }}</span></span>
                    <span>Date: {{ \Carbon\Carbon::parse($order['created_at'])->format('d M, Y') }}</span>
                </div>

                @foreach($order['slices'] as $slice)
                <div class="mb-10">
                    <div class="flex justify-between items-center mb-6">
                        <div class="text-center md:text-left">
                            <h3 class="text-3xl font-black text-gray-800">{{ $slice['origin']['iata_code'] }}</h3>
                            <p class="text-sm text-gray-500">{{ $slice['origin']['city_name'] }}</p>
                        </div>
                        
                        <div class="flex-grow flex flex-col items-center px-8">
                            <div class="w-full flex items-center">
                                <div class="w-2 h-2 rounded-full bg-blue-600"></div>
                                <div class="flex-grow h-px bg-blue-200 relative mx-2">
                                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 text-xl">✈️</span>
                                </div>
                                <div class="w-2 h-2 rounded-full border-2 border-blue-600 bg-white"></div>
                            </div>
                            <p class="text-xs font-bold text-blue-500 mt-2 uppercase tracking-widest">
                                {{ str_replace(['PT', 'H', 'M'], ['', 'H ', 'M'], $slice['duration']) }}
                            </p>
                        </div>

                        <div class="text-center md:text-right">
                            <h3 class="text-3xl font-black text-gray-800">{{ $slice['destination']['iata_code'] }}</h3>
                            <p class="text-sm text-gray-500">{{ $slice['destination']['city_name'] }}</p>
                        </div>
                    </div>

                    @foreach($slice['segments'] as $segment)
                    <div class="bg-gray-50 rounded-xl p-4 flex items-center justify-between border border-gray-100">
                        <div class="flex items-center space-x-4">
                            <div class="p-2 bg-white rounded-lg shadow-sm font-bold text-blue-600 text-sm">
                                {{ $segment['marketing_carrier']['iata_code'] }} {{ $segment['marketing_carrier_flight_number'] }}
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 uppercase font-bold">Departure</p>
                                <p class="text-sm font-semibold">{{ \Carbon\Carbon::parse($segment['departing_at'])->format('h:i A') }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400 uppercase font-bold">Arrival</p>
                            <p class="text-sm font-semibold">{{ \Carbon\Carbon::parse($segment['arriving_at'])->format('h:i A') }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endforeach

                <div class="dotted-line pt-8">
                    <h4 class="text-gray-400 uppercase text-xs font-black tracking-widest mb-4">Passenger Details</h4>
                    @foreach($order['passengers'] as $passenger)
                    <div class="flex justify-between items-center bg-blue-50 p-4 rounded-2xl">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                                {{ strtoupper(substr($passenger['given_name'], 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-bold text-gray-800 capitalize">{{ $passenger['title'] }}. {{ $passenger['given_name'] }} {{ $passenger['family_name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $passenger['email'] }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Seat Class</p>
                            <p class="text-sm font-bold text-blue-700">Economy</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-gray-50 p-6 flex flex-col md:flex-row gap-4 justify-center items-center">
                <button onclick="window.print()" class="w-full md:w-auto bg-gray-800 text-white px-8 py-3 rounded-xl font-bold hover:bg-black transition shadow-lg">
                    Print Ticket
                </button>
                <a href="{{route('search.airlines')}}" class="w-full md:w-auto bg-white border border-gray-300 text-gray-700 px-8 py-3 rounded-xl font-bold hover:bg-gray-100 text-center transition">
                    Back to Search
                </a>
            </div>
        </div>

        <p class="text-center text-gray-400 text-xs mt-8 italic">
            Thank you for choosing our service. A copy of this confirmation has been sent to your email.
        </p>
    </div>
</body>
</html>