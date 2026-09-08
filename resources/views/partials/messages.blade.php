@foreach(['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $class)
@if(session($key))<div class="alert alert-{{ $class }}" role="status">{{ session($key) }}</div>@endif
@endforeach
@if($errors->any())<div class="alert alert-danger" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
