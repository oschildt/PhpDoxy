<div id="breadcrumbs">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
            
                <!-- if label -->
                <div class="pull-left type">
                    <span class="label {object_class}">{object_type}</span>
                </div>
                <!-- endif label -->
                
                <ol class="breadcrumb pull-left">
                        
                        <!-- foreach namespace_part -->

                        <li>
                            <a href="{namespace_link}">{namespace_part}</a>
                        </li>

                        <!-- if separator -->
                        <li class="backslash">\</li>
                        <!-- endif separator -->

                        <!-- endforeach namespace_part -->
 

                        <li class="active">{object_name}</li>
                </ol>
                
            </div>
        </div>
    </div>
</div>
