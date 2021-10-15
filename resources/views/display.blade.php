


Nombre total: {{ $BaseGenerale->count() }}

<br>

@foreach($BaseGenerale as $data)

	<h4>{{ $data->id }} - {!! $data->title !!}</h4>

	<ul>
		<li>Système: {{ $data->systeme->name }}</li>
		<li>Thème: {{ $data->theme->name }}</li>
		<li>Type: {{ $data->type->name }}</li>
		<li>Date exigence: {{ $data->from_date_exigence }}</li>
		<li>
			<a href="{{ 'https://keyveille.com/'. $data->pdf }}" target="_blank">Pièce jointe</a>
		</li>
	</ul>

	<p>{!! $data->description !!}</p>

	<hr style="border: 1px solid red;">
@endforeach

Nombre total: {{ $BaseGenerale->count() }}

