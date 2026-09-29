
<div class="main-footer text-center">
    <div class="container">
        <div class="row row-sm">
            <div class="col-md-12">
                <div class="trademark footer " > &trade; مرکز یادگیری های الکترونیکی<a href="https://honaramoozesh.ir" target="_blank"> <?php echo __TITLE ?></a> &copy;
                    <?php echo jdate('Y', ''); ?>    <img src="<?php echo url('assets/img/heart.gif') ?>" width="25px" height="25px">
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php
// Only run the script if the user is logged in and has an extension
if (isset($_SESSION['admin']['dakheli']) && !empty($_SESSION['admin']['dakheli'])):
    $footer_exten = $_SESSION['admin']['dakheli'];
    ?>
    <script>
        const operatorExten = '<?php echo htmlspecialchars($footer_exten, ENT_QUOTES, 'UTF-8'); ?>';
        let currentCuid = null;
        let popupWindow = null;
        let isPolling = false; // Lock flag to prevent duplicate overlapping requests

        function checkIncomingCalls() {
            if (isPolling) return; // Skip this execution if the last request is still pending
            isPolling = true;

            const domain = window.location.origin;
            const pollUrl = `${domain}/admin/panel/call_popup.php?action=poll&exten=${encodeURIComponent(operatorExten)}`;

            fetch(pollUrl)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.has_call === true && data.caller) {
                        if (currentCuid !== data.cuid) {
                            currentCuid = data.cuid;
                            const callerNumber = data.caller;
                            const newTabUrl = `${domain}/admin/call_status.php?phone=${encodeURIComponent(callerNumber)}&cuid=${encodeURIComponent(data.cuid)}`;

                            const popupWidth = 850;
                            const popupHeight = 700;
                            const left = 10;
                            const top = window.screen.height - popupHeight - 50;
                            const features = `width=${popupWidth},height=${popupHeight},left=${left},top=${top},menubar=no,toolbar=no,resizable=yes,scrollbars=yes,status=no,location=no`;

                            if (popupWindow && !popupWindow.closed) {
                                popupWindow.location.href = newTabUrl;
                                popupWindow.focus();
                            } else {
                                popupWindow = window.open(newTabUrl, 'simotelPopup', features);
                            }
                        }
                    } else {
                        currentCuid = null;
                    }
                })
                .catch(error => {
                    console.error("Polling error:", error);
                    currentCuid = null;
                })
                .finally(() => {
                    isPolling = false; // Release the lock whether the request succeeds or fails
                });
        }

        setInterval(checkIncomingCalls, 3000);
    </script>
<?php endif; ?>

<!-- End Page -->
<!-- Back-to-top -->
<a href="#top" id="back-to-top"><i class="fe fe-arrow-up"></i></a>
<!-- Jquery js-->
<script src="<?php echo url('assets/plugins/jquery/jquery.min.js') ?>"></script>
<!-- Bootstrap js-->
<script src="<?php echo url('assets/plugins/bootstrap/js/popper.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/bootstrap/js/bootstrap-rtl.js') ?>"></script>
<!-- Perfect-scrollbar js -->
<script src="<?php echo url('assets/plugins/perfect-scrollbar/perfect-scrollbar.min-rtl.js') ?>"></script>
<!-- Internal Jquery-Ui js-->
<script src="<?php echo url('assets/plugins/jquery-ui/ui/widgets/datepicker.js') ?> "></script>
<!-- Sidemenu js -->
<script src="<?php echo url('assets/plugins/sidemenu/sidemenu-rtl.js')?>?v=20260929b"></script>
<!-- Sidebar js -->
<script src="<?php echo url('assets/plugins/sidebar/sidebar-rtl.js') ?>"></script>
<!-- Peity js-->
<script src="<?php echo url('assets/plugins/peity/jquery.peity.min.js') ?>"></script>
<!-- Internal Ion-rangeslider js-->
<script src="<?php echo url('assets/plugins/ion-rangeslider/js/ion.rangeSlider.min.js') ?>"></script>
<!-- Internal Dashboard js-->
<script src="<?php echo url('assets/js/index.js') ?>"></script>
<!-- Internal Specturm-colorpicker js-->
<script src="<?php echo url('assets/plugins/spectrum-colorpicker/spectrum.js') ?>"></script>
<!-- Sticky js -->
<script src="<?php echo url('assets/js/sticky.js') ?>"></script>
<!-- Custom js -->
<script src="<?php echo url('assets/js/custom.js') ?>"></script>

<!-- Select2 js-->
<script src="<?php echo url('assets/plugins/select2/js/select2.min.js') ?>"></script>
<!-- Internal Form-elements js-->
<script src="<?php echo url('assets/js/advanced-form-elements.js') ?>"></script>
<script src="<?php echo url('assets/js/form-elements.js') ?>"></script>
<script src="<?php echo url('assets/js/select2.js') ?>"></script>
<script src="<?php echo url('assets/plugins/prism/prism.js') ?>"></script>
<script src="<?php echo url('assets/js/form-validation.js') ?>"></script>
<script src="<?php echo url('assets/plugins/parsleyjs/parsley.min.js') ?>"></script>
<!-- Internal Jquery.maskedinput js-->
<script src="<?php echo url('assets/plugins/jquery.maskedinput/jquery.maskedinput.js') ?>"></script>
<!-- InternalSumoselect js-->
<script src="<?php echo url('assets/plugins/sumoselect/jquery.sumoselect.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/jquery.dataTables.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/dataTables.bootstrap4.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/dataTables.responsive.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/dataTables.buttons.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/buttons.bootstrap4.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/jszip.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/pdfmake.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/vfs_fonts.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/buttons.html5.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/buttons.print.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/datatable/fileexport/buttons.colVis.min.js') ?>"></script>
<script src="<?php echo url('assets/js/table-data.js') ?>"></script>
<script src="<?php echo url('assets/js/circle-progress.min.js') ?>"></script>
<script src="<?php echo url('assets/js/chart-circle.js') ?>"></script>
<script src="<?php echo url('assets/plugins/chart/chart.js') ?>"></script>
<script src="<?php echo url('assets/plugins/jspdf/jspdf.umd.min.js') ?>"></script>
<script src="<?php echo url('assets/plugins/html2canvas/html2canvas.min.js') ?>"></script>
<script src="<?php echo url('assets/js/fullcalendar.min.js') ?>"></script>
<script src="<?php echo url('assets/js/datepicker.min.js') ?>"></script>
