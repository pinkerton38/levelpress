<html>
<head>
    <title>CSV2PDF</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <br><br>

    <div class="row">
        <div class="col-md-8 col-md-offset-2 col-sm-8 col-sm-offset-2 col-xs-10 col-xs-offset-1">
            <div class="well well-lg">
                <form action="convert.php" method="post" enctype="multipart/form-data" class="form-horizontal">
                    <div class="form-group">
                        <label for="csv" class="col-md-3 col-sm-3 control-label">CSV файл</label>

                        <div class="col-md-5 col-sm-5 ">
                            <input type="file" name="csv" id="csv" class="form-control">
                        </div>

                        <button type="submit" class="btn btn-primary" title="Преобразовать" name="convert">
                            .<span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="bootstrap/js/jquery-2.1.3.min.js"></script>
<script src="bootstrap/js/bootstrap.min.js"></script>
</body>
</html>


