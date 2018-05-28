@extends('panneau::index')

@section('body')
<div class="container">
    <div class="row justify-content-md-center">
        <div class="col-md-7">
            <div class="card mt-4">
                <div class="card-header">@lang('panneau::auth.login_title')</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('panneau.auth.login') }}">
                        {{ csrf_field() }}

                        <div class="form-group row">
                            <label for="email" class="col-md-3 control-label">@lang('panneau::auth.email_label')</label>

                            <div class="col-md-9">
                                <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" required autofocus>

                                @if ($errors->has('email'))
                                    <div class="invalid-feedback">
                                        {{ $errors->first('email') }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="password" class="col-md-3 control-label align-middle mb-0">@lang('panneau::auth.password_label')</label>

                            <div class="col-md-7">
                                <input id="password" type="password" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="password" required>

                                @if ($errors->has('password'))
                                    <div class="invalid-feedback">
                                        {{ $errors->first('password') }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-9 offset-md-3">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}> @lang('panneau::auth.remember_me')
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-md-8 offset-md-3">
                                <button type="submit" class="btn btn-primary">
                                    @lang('panneau::auth.login_btn')
                                </button>

                                <a class="btn btn-link" href="{{ route('panneau.auth.password.request') }}">
                                    @lang('panneau::auth.forgot_link')
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
