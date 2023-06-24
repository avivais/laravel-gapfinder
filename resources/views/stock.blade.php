<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $symbol }} Stock Data</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <h2>{{ $symbol }} Stock Data</h2>
    <div class="container">
        <table class="table table-striped table-hover table-bordered">
            <thead class="table-primary">
                <tr>
                    <th>Date</th>
                    <th>Open</th>
                    <th>High</th>
                    <th>Low</th>
                    <th>Close</th>
                    <th>Volume</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stockData as $data)
                    <tr>
                        <td>{{ $data['date']->toDateString() }}</td>
                        <td>{{ $data['open'] }}</td>
                        <td>{{ $data['high'] }}</td>
                        <td>{{ $data['low'] }}</td>
                        <td>{{ $data['close'] }}</td>
                        <td>{{ $data['volume'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
