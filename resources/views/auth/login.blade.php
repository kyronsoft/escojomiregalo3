@extends('admin.authentication.master')

@section('title')
    login
    {{ $title }}
@endsection

@push('css')
    <style>
        .login-img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        /* En pantallas grandes (laptop y más, >=1200px en Bootstrap) */
        @media (min-width: 1200px) {
            .login-img {
                width: 750px;
                height: 907px;
                object-fit: cover;
                /* mantiene proporciones y recorta si es necesario */
            }
        }
    </style>
@endpush

@section('content')
    <section>
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-5"><img class="bg-img-cover bg-center login-img"
                        src="{{ asset('assets/images/moreproducts/loginpage.png') }}" alt="loginpage" /></div>
                <div class="col-xl-7 p-0">
                    <div class="login-card">
                        <form class="theme-form login-form" action="{{ route('login') }}" method="post">
                            @csrf
                            <h4>Ingreso al Sistema</h4>
                            <h6>Bienvenido!</h6>
                            <div class="form-group">
                                <label>Documento</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="icon-user"></i></span>
                                    <input class="form-control @error('documento') is-invalid @enderror" type="documento"
                                        id="documento" name="documento" value="{{ old('documento') }}"
                                        placeholder="Correo Registrado" autofocus />

                                    @error('documento')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="icon-lock"></i></span>
                                    <input class="form-control @error('password') is-invalid @enderror" type="password"
                                        name="password" id="password" required autocomplete="current-password"
                                        placeholder="*********" />

                                    @error('password')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                    {{-- <div class="show-hide"><span class="show"> </span></div> --}}
                                </div>
                            </div>
                            <div class="form-group"><button class="btn btn-primary btn-block"
                                    type="submit">Ingresar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>


    @push('scripts')
    @endpush
@endsection
