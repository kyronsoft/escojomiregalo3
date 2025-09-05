<!DOCTYPE html>
<html lang="en" @if (Route::current()->getName() == 'layout-rtl') dir="rtl" @endif
    @switch(Route::current()->getName())
      @case('layout-rtl')
          dir="rtl"
          @break
  @endswitch>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="viho admin is super flexible, powerful, clean &amp; modern responsive bootstrap 4 admin template with unlimited possibilities. laravel/framework: ^8.40">
    <meta name="keywords"
        content="admin template, viho admin template, dashboard template, flat admin template, responsive admin template, web app">
    <meta name="author" content="pixelstrap">
    <link rel="icon" sizes="any" href="{{ asset('assets/images/moreproducts/favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32"
        href="{{ asset('assets/images/moreproducts/png/favicon-login-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192"
        href="{{ asset('assets/images/moreproducts/png/favicon-login-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180"
        href="{{ asset('assets/images/moreproducts/png/favicon-login-180x180.png') }}">
    <title>@yield('title')</title>
    <!-- Google font-->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&amp;display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&amp;display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Rubik:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,300;1,400;1,500;1,600;1,700;1,800;1,900&amp;display=swap"
        rel="stylesheet">
    <!-- Font Awesome-->
    @includeIf('layouts.admin.partials.css')
</head>

@switch(Route::current()->getName())
    @case('boxed-layout')

        <body class="box-layout">
        @break

        @case('layout-rtl')

            <body class="rtl">
            @break

            @case('layout-dark')

                <body class="dark-only">
                @break

                @default

                    <body>
                @endswitch
                <!-- Loader starts-->
                <div class="loader-wrapper">
                    <div class="theme-loader"></div>
                </div>
                <!-- Loader ends-->
                <!-- page-wrapper Start-->
                <div class="page-wrapper compact-sidebar" id="pageWrapper">
                    <!-- Page Header Start-->
                    @includeIf('layouts.admin.partials.header')
                    <!-- Page Header Ends -->
                    <!-- Page Body Start-->
                    <div class="page-body-wrapper sidebar-icon">
                        <!-- Page Sidebar Start-->
                        @includeIf('layouts.admin.partials.sidebar')
                        <!-- Page Sidebar Ends-->
                        <div class="page-body">
                            <!-- Container-fluid starts-->
                            @yield('content')
                            <!-- Container-fluid Ends-->
                        </div>
                        <!-- footer start-->
                        @switch(Route::current()->getName())
                            @case('footer-dark')
                                <footer class="footer footer-dark">
                                @break

                                @case('footer-fixed')
                                    <footer class="footer footer-fix">
                                    @break

                                    @default
                                        <footer>
                                    @endswitch
                                    <div class="container-fluid">
                                        <div class="row">
                                            <div class="col-md-6 footer-copyright">
                                                <p class="mb-0">Copyright
                                                    {{ date('Y') }}-{{ date('y', strtotime('+1 year')) }} © viho
                                                    All rights reserved.</p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="pull-right mb-0">Hand crafted & made with <i
                                                        class="fa fa-heart font-secondary"></i></p>
                                            </div>
                                        </div>
                                    </div>
                                </footer>
                    </div>
                </div>
                <!-- latest jquery-->
                @includeIf('layouts.admin.partials.js')
                @stack('scripts')
            </body>

</html>
