<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <title>Queue System</title>
</head>
<body>
    <div class="container">
        <h1>Queue System</h1>
        <form id="queueForm">
            <div class="form-group">
                <label for="room">Select Room:</label>
                <select id="room" class="form-control">
                    <option value="Room 1">Room 1</option>
                    <option value="Room 2">Room 2</option>
                    <option value="Room 3">Room 3</option>
                    <option value="Room 4">Room 4</option>
                    <option value="Room 5">Room 5</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Create Queue</button>
        </form>
        <div id="queueDisplay" class="mt-4"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        $('#queueForm').on('submit', function(e) {
            e.preventDefault();
            const room = $('#room').val();
            $.post('/queue', {room: room}, function(data) {
                $('#queueDisplay').append(`<p>Room: ${data.room}, Queue Number: ${data.queue_number}</p>`);
            });
        });
    </script>
</body>
</html>