@props(['status'])
<span class="label label-{{ ['Present'=>'success','Absent'=>'danger','Off Day'=>'default','Leave'=>'warning'][$status] ?? 'info' }}">{{ $status ?: 'Unrecorded' }}</span>
