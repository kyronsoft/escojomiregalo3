<header class="main-nav">
    <div class="sidebar-user text-center">
        <img class="img-90 rounded-circle" src="{{ asset('assets/images/dashboard/1.png') }}" alt="" />
        <div class="badge-bottom"><span class="badge badge-primary">New</span></div>
        <a href="user-profile">
            <h6 class="mt-3 f-14 f-w-600">{{ Auth::user()->name }}</h6>
        </a>
        <p class="mb-0 font-roboto">{{ Auth::user()->getRoleNames()->first() ?? 'Sin rol' }}</p>
    </div>
    <nav>
        <div class="main-navbar">
            <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
            <div id="mainnav">
                <ul class="nav-menu custom-scrollbar">
                    <li class="back-btn">
                        <div class="mobile-back text-end"><span>Back</span><i class="fa fa-angle-right ps-2"
                                aria-hidden="true"></i></div>
                    </li>
                    <li class="sidebar-main-title">
                        <div>
                            <h6>General</h6>
                        </div>
                    </li>
                    <li class="dropdown">
                        <a class="nav-link menu-title {{ prefixActive('/dashboard') }}" href="javascript:void(0)"><i
                                data-feather="home"></i><span>Dashboard</span></a>
                        <ul class="nav-submenu menu-content" style="display: {{ prefixBlock('/dashboard') }};">
                            <li><a href="{{ route('dashboard') }}" class="{{ routeActive('dashboard') }}">Ecommerce</a>
                            </li>
                        </ul>
                    </li>
                    <li class="sidebar-main-title">
                        <div>
                            <h6>Opciones</h6>
                        </div>
                    </li>
                    <li class="dropdown">
                        <a class="nav-link menu-title {{ prefixActive('/empresas') }}" href="javascript:void(0)">
                            <i data-feather="layout"></i>
                            <span>Empresas</span>
                        </a>
                        <ul class="nav-submenu menu-content" style="display: {{ prefixBlock('/empresas') }};">
                            <li>
                                <a href="{{ route('empresas.create') }}" class="{{ routeActive('empresas.create') }}">
                                    Crear Empresa
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('empresas.index') }}" class="{{ routeActive('empresas.index') }}">
                                    Listar Empresas
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="dropdown">
                        <a class="nav-link menu-title {{ prefixActive('/campaigns') }}" href="javascript:void(0)">
                            <i data-feather="layout"></i>
                            <span>Campañas</span>
                        </a>
                        <ul class="nav-submenu menu-content" style="display: {{ prefixBlock('/campaigns') }};">
                            <li>
                                <a href="{{ route('campaigns.create') }}"
                                    class="{{ routeActive('campaigns.create') }}">
                                    Crear Campaña
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('campaigns.index') }}" class="{{ routeActive('campaigns.index') }}">
                                    Listar Campañas
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="dropdown">
                        <a class="nav-link menu-title {{ prefixActive('/colaboradores') }}" href="javascript:void(0)">
                            <i data-feather="layout"></i>
                            <span>Colaboradores</span>
                        </a>
                        <ul class="nav-submenu menu-content" style="display: {{ prefixBlock('/colaboradores') }};">
                            {{-- <li>
                                <a href="{{ route('colaboradores.create') }}"
                                    class="{{ routeActive('colaboradores.create') }}">
                                    Crear Colaborador
                                </a>
                            </li> --}}
                            <li>
                                <a href="{{ route('colaboradores.index') }}"
                                    class="{{ routeActive('colaboradores.index') }}">
                                    Listar Colaboradores
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('colaboradores.import') }}"
                                    class="{{ routeActive('colaboradores.import') }}">
                                    Importar Colaboradores
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="dropdown">
                        <a class="nav-link menu-title {{ prefixActive('/campaign_toys') }}" href="javascript:void(0)">
                            <i data-feather="layout"></i>
                            <span>Referencias</span>
                        </a>
                        <ul class="nav-submenu menu-content" style="display: {{ prefixBlock('/campaign_toys') }};">
                            {{-- <li>
                                <a href="{{ route('campaign_toys.create') }}"
                                    class="{{ routeActive('campaign_toys.create') }}">
                                    Crear Referencia
                                </a>
                            </li> --}}
                            <li>
                                <a href="{{ route('campaign_toys.index') }}"
                                    class="{{ routeActive('campaign_toys.index') }}">
                                    Listar Referencias
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('campaign_toys.import') }}"
                                    class="{{ routeActive('campaign_toys.import') }}">
                                    Importar Referencias
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="dropdown">
                        <a class="nav-link menu-title {{ prefixActive('/admin/importerrors') }}"
                            href="javascript:void(0)">
                            <i data-feather="layout"></i>
                            <span>Utilidades</span>
                        </a>
                        <ul class="nav-submenu menu-content"
                            style="display: {{ prefixBlock('/admin/importerrors') }};">
                            {{-- <li>
                                <a href="{{ route('campaign_toys.create') }}"
                                    class="{{ routeActive('campaign_toys.create') }}">
                                    Crear Referencia
                                </a>
                            </li> --}}
                            <li>
                                <a href="{{ route('/admin/importerrors') }}"
                                    class="{{ routeActive('/admin/importerrors') }}">
                                    Errores Importación
                                </a>
                            </li>

                        </ul>
                    </li>
                </ul>
            </div>
            <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
        </div>
    </nav>
</header>
