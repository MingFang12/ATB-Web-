<?php
$user_id= $this->session->userdata('user_id');

if(!$user_id){
    redirect(route('admin.auth.login'));
}
?>

<!DOCTYPE html>

<!--[if IE 8]> <html lang="en" class="ie8 no-js"> <![endif]-->
<!--[if IE 9]> <html lang="en" class="ie9 no-js"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en">
<!--<![endif]-->
<!-- BEGIN HEAD -->
<?php echo $header_layout;?>
<!-- END HEAD -->

<body class="page-header-fixed page-sidebar-closed-hide-logo page-content-white page-sidebar-fixed">
<div class="page-wrapper">

    <!-- BEGIN CONTAINER -->
    <div class="page-container" style="margin-top: 0px;">
        <!-- BEGIN SIDEBAR -->
        <?php echo $sidebar_layout;?>
        <!-- END SIDEBAR -->

        <!-- BEGIN CONTENT -->
        <div class="page-content-wrapper">
            <!-- BEGIN CONTENT BODY -->
            <div class="page-content">
                <div class="full-height-content full-height-content-scrollable">
                    <div class="full-height-content-body">

                        <div class="portlet light">
                            <div class="portlet-title">
                                <div class="caption">
                                    <span class="caption-subject bold uppercase">Block Post</span>
                                </div>

                            </div>
                            <div class="portlet-body">
                                <form class="newAdminForm" action="<?php echo route('admin.signups.block_post');?>" method="get" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="blockReason">Reason for Blocking</label>
                                        <textarea class="form-control" id="blockReason" name="blockReason" rows="10"></textarea>
                                    </div>
                                    <input type="hidden" id="block_postid" name="block_postid" value="<?php echo $block_postid;?>">
                                    <button type="submit" class="btn btn-primary">Block Post</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- END CONTENT BODY -->
        </div>
        <!-- END CONTENT -->

    </div>
    <!-- END CONTAINER -->

</div>

<?php echo $footer_layout;?>
<script>
    $(document).ready(function()
    {
//                $('#clickmewow').click(function()
//                {
//                    $('#radio1003').attr('checked', 'checked');
//                });
    })
</script>
</body>

</html>