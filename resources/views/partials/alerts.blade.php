@if(session('success'))<div class="alert success" role="status"><x-icon name="check"/><span>{{ session('success') }}</span></div>@endif
@if(session('warning'))<div class="alert warning" role="status"><x-icon name="alert"/><span>{{ session('warning') }}</span></div>@endif
@if($errors->any())<div class="alert danger" role="alert" tabindex="-1" id="form-errors"><x-icon name="alert"/><div><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
