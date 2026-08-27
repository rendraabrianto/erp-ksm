@extends('adminlte::auth.auth-page', ['auth_type' => 'login'])

@section('auth_header', 'Login ERP KSM Group')

@section('auth_body')

    {{-- Session Status --}}
    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Email --}}
        <div class="input-group mb-3">
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                placeholder="Email"
                required
                autofocus
                autocomplete="username"
            >

            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-envelope"></span>
                </div>
            </div>

            @error('email')
                <span class="invalid-feedback">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        {{-- Password --}}
        <div class="input-group mb-3">
            <input
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                placeholder="Password"
                required
                autocomplete="current-password"
            >

            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
            </div>

            @error('password')
                <span class="invalid-feedback">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="row">

            {{-- Remember Me --}}
            <div class="col-7">
                <div class="icheck-primary">
                    <input
                        type="checkbox"
                        name="remember"
                        id="remember"
                        {{ old('remember') ? 'checked' : '' }}
                    >

                    <label for="remember">
                        Remember Me
                    </label>
                </div>
            </div>

            {{-- Login Button --}}
            <div class="col-5">
                <button
                    type="submit"
                    class="btn btn-primary btn-block"
                >
                    <i class="fas fa-sign-in-alt mr-1"></i>
                    Login
                </button>
            </div>

        </div>
    </form>

@stop

@section('auth_footer')

    <div class="text-center text-muted">
        <small>
            ERP KSM Group
        </small>
    </div>

@stop