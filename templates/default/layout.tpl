<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">

        <link rel="stylesheet" href="resources/bootstrap.min.css">
        <link rel="stylesheet" href="resources/style.css">
        <link rel="stylesheet" href="resources/jquery.iviewer.css">

        <title>
            {title}
        </title>
    </head>

    <body>
        {menu}
        
        {breadcrumbs}
        
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    {contents}
                    
                    <div id="footer">
                        Generated on {gen_date} with <a href="http://www.google.com" target="_blank">DocGen</a>
                    </div>                    
                </div>
            </div>
        </div>

        <script src="resources/jquery-3.2.1.min.js"></script>
        <script src="resources/jquery-ui-1.12.1.min.js"></script>

        <script src="elementlist.js"></script>
        <script src="resources/main.js"></script>
        
        <script src="resources/jquery.iviewer.js" type="text/javascript"></script>
        <script src="resources/jquery.mousewheel.js" type="text/javascript"></script>
        
        <script type="text/javascript">
            $(window).resize(function(){
                $("#viewer").height(1400);
            });

            $(document).ready(function() {
                $("#viewer").iviewer({src: 'resources/classes.svg', zoom_animation: false});
                $('#viewer img').bind('dragstart', function(event){
                    event.preventDefault();
                });
                $(window).resize();
            });
        </script>
        
    </body>
</html>


