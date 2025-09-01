<script src="{{ asset('assets/js/jquery-3.5.1.min.js') }}"></script>
<!-- feather icon js-->
<script src="{{ asset('assets/js/icons/feather-icon/feather.min.js') }}"></script>
<script src="{{ asset('assets/js/icons/feather-icon/feather-icon.js') }}"></script>
<!-- Sidebar jquery-->
<script src="{{ asset('assets/js/sidebar-menu.js') }}"></script>
<script src="{{ asset('assets/js/config.js') }}"></script>
<!-- Bootstrap js-->
<script src="{{ asset('assets/js/bootstrap/popper.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap/bootstrap.min.js') }}"></script>
<!-- Plugins JS start-->
@stack('scripts')
<!-- Plugins JS Ends-->
<!-- Theme js-->
<script src="{{ asset('assets/js/script.js') }}"></script>
<!-- Plugin used-->

<script>
    $(document).ready(function() {
        let colorTheme = false;
        let defaultTheme = localStorage.getItem('dark');
        var dirValue = $('html').attr('dir');

        // Function to get the value of 'dir' attribute
        function getDirAttributeValue() {
            dirValue = $('html').attr('dir');
            // Perform actions based on the 'dir' value here
        }
        getDirAttributeValue();

        $("body").attr("class", defaultTheme);
        $(".mode").on("click", function() {
            colorTheme = !colorTheme;
            console.log(dirValue);
            if (dirValue == 'rtl') {
                if (colorTheme) {
                    $("body").attr("class", "rtl dark-only");
                    localStorage.setItem("dark", "dark-only");
                } else {
                    $("body").attr("class", "rtl");
                    localStorage.setItem("dark", "light");
                }
            } else {
                if (colorTheme) {
                    $("body").attr("class", "dark-only");
                    localStorage.setItem("dark", "dark-only");
                } else {
                    $("body").attr("class", "");
                    localStorage.setItem("dark", "light");
                }
            }
        });
        setInterval(getDirAttributeValue, 5000);
    });
</script>
