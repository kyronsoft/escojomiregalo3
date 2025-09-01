<style>
    .nav-right {
        display: flex;
        align-items: center;
        /* alinea verticalmente */
        justify-content: space-between;
        /* separa extremos: izq la img, der el botón */
        width: 100%;
        /* asegúrate que tome todo el ancho disponible */
    }

    .img-right {
        height: 40px;
        margin-top: 20px;
        /* mobile first */
    }

    @media (min-width: 992px) {
        .img-right {
            margin-top: 0.5em;
            /* laptops */
        }
    }

    .nav-menus {
        margin: 0;
        padding: 0;
        list-style: none;
    }
</style>

<div class="page-main-header">
    <div class="main-header-right row m-0">
        <div class="main-header-left">
            <div class="logo-wrapper">
                <a href="{{ route('dashboard') }}">
                    <img class="img-fluid" style="max-width: 110px;"
                        src="{{ asset('assets/images/moreproducts/loginpage.png') }}" alt="">
                </a>
            </div>
            <div class="dark-logo-wrapper">
                <a href="{{ route('dashboard') }}">
                    <img class="img-fluid" style="max-width: 110px;"
                        src="{{ asset('assets/images/moreproducts/loginpage.png') }}" alt="">
                </a>
            </div>
            <div class="toggle-sidebar">
                <i class="status_toggle middle" data-feather="align-center" id="sidebar-toggle"></i>
            </div>
        </div>

        <div class="nav-right col right-menu p-0">
            <!-- Imagen al extremo izquierdo -->
            <img src="{{ asset('assets/images/moreproducts/Logo_More.png') }}" class="img-right" alt="Icono">

            <!-- Botón al extremo derecho -->
            <ul class="nav-menus">
                <li class="onhover-dropdown p-0">
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button class="btn btn-primary-light" type="submit">
                            <i data-feather="log-out"></i>Salir
                        </button>
                    </form>
                </li>
            </ul>
        </div>

        <div class="d-lg-none mobile-toggle pull-right w-auto">
            <i data-feather="more-horizontal"></i>
        </div>
    </div>
</div>
