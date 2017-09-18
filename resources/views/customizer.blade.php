@extends('layouts.building')
@include('_rom_loader')
@include('_rom_settings')
@include('custom/_items')
@include('custom/_switches')

@section('content')
@yield('loader')
<form id="plando">
	<input type="hidden" id="seed" name="seed" value="0" />
	<input type="hidden" name="logic" value="None" />
	<input type="hidden" name="difficulty" value="custom" />
	<input type="hidden" name="variation" value="none" />
	<input type="hidden" name="mode" value="standard" />
	<input type="hidden" name="goal" value="ganon" />
	<input type="hidden" name="heart_speed" value="half" />
	<input type="hidden" name="sram_trace" value="false" />
	<input type="hidden" name="debug" value="false" />
	<div id="seed-generate" class="panel panel-success">
		<div class="panel-heading">
			<h3 class="panel-title">Customizer: Just because you can, doesn't mean you should</h3>
			@yield('rom-settings-button')
		</div>
		<div class="panel-body">
			<div class="row" style="padding-bottom:5px;">
				<div class="col-md-6">
					<div class="input-group" role="group">
						<span class="input-group-addon">Name</span>
						<input type="text" id="seed" class="seed form-control" placeholder="name this">
					</div>
				</div>
				<div class="col-md-6">
					<div class="input-group" role="group">
						<span class="input-group-addon">Logic</span>
						<select id="logic" class="form-control selectpicker">
							<option value="None">None (I know what I'm doing)</option>
							<option value="NoMajorGlitches">No Glitches</option>
							<option value="OverworldGlitches">Overworld Glitches</option>
							<option value="MajorGlitches">Major Glitches</option>
						</select>
					</div>
				</div>
				<div class="col-md-6">
					<div class="btn-group btn-flex" role="group">
						<button name="reset" class="btn btn-danger">Reset Everything</button>
					</div>
				</div>
				<div class="col-md-6">
					<div class="btn-group btn-flex" role="group">
						<button name="generate" class="btn btn-success">Generate ROM</button>
					</div>
				</div>
			</div>
			@yield('rom-settings')
		</div>
	</div>
<div id="seed-details" class="info panel panel-info">
	<div class="panel-heading"><h3 class="panel-title">Game Details</h3></div>
	<div class="panel-body">
		<div class="col-md-6">
			<div>Logic: <span class="logic"></span></div>
			<div>ROM build: <span class="build"></span></div>
			<div>Difficulty: <span class="difficulty"></span></div>
			<div>Variation: <span class="variation"></span></div>
			<div>Mode: <span class="mode"></span></div>
			<div>Goal: <span class="goal"></span></div>
			<div>Seed: <span class="seed"></span></div>
		</div>
		<div class="col-md-6">
			<div class="row">
				<button name="save-spoiler" class="btn btn-default" disabled>Save Spoiler</button>
				<button name="save" class="btn btn-success" disabled>Save Rom</button>
			</div>
		</div>
		<div class="spoiler col-md-12">
			<div class="spoiler-toggle"><span class="glyphicon glyphicon-plus"></span> Spoiler!</div>
			<div class="spoiler-tabed">
				<div class="col-md-6"></div>
				<div class="col-md-6">
					<select id="spoiler-search" class="form-control selectpicker" data-live-search="true" data-dropup-auto="false" title="Search for Item">
					</select>
				</div>
				<ul class="nav nav-pills" role="tablist">
				</ul>
				<div class="tab-content">
				</div>
			</div>
			<div id="spoiler" class="spoiler-text" style="display:none"></div>
		</div>
	</div>
</div>
<div class="tab-content">
	<div class="tab-pane" id="custom-item-select">
		<div class="total-items"><span id="custom-count">0</span> / <span id="custom-count-total">0</span></div>
		@yield('itemselect')
	</div>
	<div class="tab-pane" id="custom-settings">
		@yield('switches')
	</div>
@foreach($world->getRegions() as $name => $region)
	<div class="tab-pane" id="custom-region-{{ str_replace(' ', '_', $name) }}">
		<div class="panel panel-default">
			<div class="panel-heading">{{ $name }}</div>
			<table class="table table-striped">
			@foreach($region->getLocations() as $location)
				<?php if ($location instanceof ALttP\Location\Prize\Event) continue; ?>
				<tr>
					<td>{{ $location->getName() }}</td>
					<td>
						<select class="{{ $location_class[get_class($location)] ?? 'items' }}" name="l[{{ base64_encode($location->getName()) }}]"></select>
					</td>
				</tr>
			@endforeach
			</table>
		</div>
	</div>
@endforeach
</form>

<script>
function getFormData($form){
	var unindexed_array = $form.serializeArray();
	var indexed_array = {};

	$.map(unindexed_array, function(n, i){
		if (n['value'] == 'auto_fill') return;
		console.log(n['name']);
		indexed_array[n['name']] = n['value'];
	});

	return indexed_array;
}
function seedApplied(data) {
	return new Promise(function(resolve, reject) {
		$('button[name=generate]').html('Generate ROM').prop('disabled', false);
		$('.spoiler').show();
		$('#spoiler').html('<pre>' + JSON.stringify(data.spoiler, null, 4) + '</pre>');
		pasrseSpoilerToTabs(data.spoiler);
		return resolve('yes');
	});
}
function seedFailed(data) {
	return new Promise(function(resolve, reject) {
		alert('Seed failed to Gen YO!');
		$('button[name=generate]').html('Generate ROM').prop('disabled', false);
		return resolve('no');
	});
}
function applySeed(rom, seed) {
	return new Promise(function(resolve, reject) {
		console.log('called');
		$.post('/seed' + (seed ? '/' + seed : ''), getFormData($('form')), function(patch) {
			rom.parsePatch(patch.patch).then(getSprite($('#sprite-gfx').val())
			.then(rom.parseSprGfx)
			.then(rom.setMusicVolume($('#generate-music-on').prop('checked')))
			.then(rom.setHeartSpeed($('#heart-speed').val()))
			.then(function(rom) {
				$('.info').show();
				$('button[name=save], button[name=save-spoiler]').prop('disabled', false);
				resolve({rom: rom, patch: patch});
			}));
		}, 'json')
		.fail(reject);
	});
}

$('#spoiler-search').on('changed.bs.select', function() {
	var string = $(this).val();
	tabsContent.forEach(function(val, nav) {
		var numItems = val.reduce(function(n, item) {
			return n + (item == string);
		}, 0);
		$('#n-' + nav + ' .badge').html(numItems || null);
	});
});

var tabsContent = new Map();
function pasrseSpoilerToTabs(spoiler) {
	var spoilertabs = $('.spoiler-tabed');
	var nav = spoilertabs.find('.nav-pills');
	var active_nav = nav.find('.active a').data('section');
	nav.html('');
	var content = spoilertabs.find('.tab-content').html('');
	var items = {};
	for (section in spoiler) {
		nav.append($('<li id="n-spoiler-' + section.replace(/ /g, '_') + '" '
			+ ((section == active_nav) ? 'class="active"' : '') + '><a data-toggle="tab" data-section="' + section
			+ '" href="#spoiler-' + section.replace(/ /g, '_') + '">' + section
			+ '<span class="badge badge-pill"></span></a></li>'));
		content.append($('<div id="spoiler-' + section.replace(/ /g, '_') + '" class="tab-pane'
			+ ((section == active_nav) ? ' active' : '') + '"><pre>' + JSON.stringify(spoiler[section], null, 4)
			+ '</pre></div>'));
		if (['meta', 'playthrough', 'Fountains', 'Medallions'].indexOf(section) === -1) {
			tabsContent.set('spoiler-' + section.replace(/ /g, '_'), Object.keys(spoiler[section]).map(function (key) {
				return spoiler[section][key];
			}));
		}
		for (loc in spoiler[section]) {
			if (['meta', 'playthrough', 'Fountains', 'Medallions'].indexOf(section) > -1) continue;
			items[spoiler[section][loc]] = true;
		}
		var sopts = '';
		Object.keys(items).sort().forEach(function(item) {
			sopts += '<option value="' + item + '">' + item + '</option>';
		});
		$('#spoiler-search').html(sopts).selectpicker('refresh');
	}
}

$(function() {
	var config = {};
	$('.items').append($('#items').html());
	$('.prizes').append($('#prizes').html());
	$('.bottles').append($('#bottles').html());
	$('.medallions').append($('#medallions').html());

	$('form').on('submit', function() {
		return false;
	});

	localforage.getItem('plandomizer').then(function(plando) {
		config = plando || {};
		for (var name in config) {
			if (!config.hasOwnProperty(name)) continue;
			$('select[name="' + name + '"]').val(config[name]);
		}
	});

	$('select').change(function() {
		config[this.name] = $(this).val();
		localforage.setItem('plandomizer', config);
	});

	// dirty cleanup function for now
	$('button[name=reset]').on('click', function(e) {
		e.preventDefault();
		config = {};
		localforage.setItem('plandomizer', config);
		localforage.setItem('vt.custom.items', config);
		window.location = window.location;
	});

	$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
		var target = $(e.target).attr("href") // activated tab
		if (!$(target).data('init')) {
			$(target + " select").selectpicker({
				hideDisabled: true,
				liveSearch: true,
			});
			$(target).data('init', true);
		}
		if (target == '#custom-item-select') {
			$('#custom-count-total').html($('.items option[value="auto_fill"]:selected').length);
			$('.custom-items').first().trigger('change');
		}
	});

	$('.custom-items').on('change click blur', function(e) {
		e.stopPropagation();
		$('#custom-count').html($('.custom-items').map(function(){return Number(this.value)}).toArray().reduce(function(a,b){return a+b}));
		if ($('#custom-count').html() != $('#custom-count-total').html()) {
			$('.total-items').removeClass('bg-success').addClass('bg-danger');
		} else {
			$('.total-items').removeClass('bg-danger').addClass('bg-success');
		}
	});
	$('.custom-items').first().trigger('change');

	$('button[name=generate]').on('click', function() {
		$('button[name=generate]').html('Generating...').prop('disabled', true);
		applySeed(rom, $('#seed').val()).then(seedApplied, seedFailed);
	});

	$('#logic').on('change', function() {
		//$('.info').hide();
		localforage.setItem('vt.custom.logic', $(this).val());
		$('input[name=logic]').val($(this).val());
	});
	localforage.getItem('vt.custom.logic').then(function(value) {
		if (value === null) return;
		$('#logic').val(value);
		$('#logic').trigger('change');
	});

	$('button[name=save]').on('click', function() {
		return rom.save('ALttP - VT_' + rom.logic
			+ '_' + rom.difficulty
			+ '-' + rom.mode
			+ '-' + rom.goal
			+ (rom.variation == 'none' ? '' : '_' + rom.variation)
			+ '_' + rom.seed + '.sfc');
	});
	$('button[name=save-spoiler]').on('click', function() {
		$.get("/spoiler_click/" + rom.seed);
		return FileSaver.saveAs(new Blob([$('.spoiler-text pre').html()]), 'ALttP - VT_' + rom.logic
			+ '_' + rom.difficulty
			+ '-' + rom.mode
			+ '-' + rom.goal
			+ (rom.variation == 'none' ? '' : '_' + rom.variation)
			+ '_' + rom.seed + '.txt');
	});
});
</script>
<script id="medallions" type="text/template">
	<option value="auto_fill">Random</option>
	@foreach($medallions as $item)
	<option value="{{ $item->getName() }}">{{ $item->getNiceName() }}</option>
	@endforeach
</script>
<script id="bottles" type="text/template">
	<option value="auto_fill">Random</option>
	@foreach($bottles as $item)
	<option value="{{ $item->getName() }}">{{ $item->getNiceName() }}</option>
	@endforeach
</script>
<script id="prizes" type="text/template">
	<option value="auto_fill">Random</option>
	@foreach($prizes as $item)
	<option value="{{ $item->getName() }}">{{ $item->getNiceName() }}</option>
	@endforeach
</script>
<script id="items" type="text/template">
	<option value="auto_fill">Random</option>
	@foreach($items as $item)
	<option value="{{ $item->getName() }}">{{ $item->getNiceName() }}</option>
	@endforeach
</script>
@overwrite