<?php

namespace ALttP\Http\Controllers;

use ALttP\Enemizer;
use ALttP\EntranceRandomizer;
use ALttP\Http\Requests\CreateRandomizedGame;
use ALttP\Jobs\SendPatchToDisk;
use ALttP\Randomizer;
use ALttP\Rom;
use ALttP\Support\WorldCollection;
use ALttP\World;
use Exception;
use GrahamCampbell\Markdown\Facades\Markdown;
use HTMLPurifier_Config;
use HTMLPurifier;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class RandomizerController extends Controller
{
    public function generateSeed(CreateRandomizedGame $request)
    {
        if ($request->has('lang')) {
            app()->setLocale($request->input('lang'));
        }

        try {
            $payload = $this->prepSeed($request);
            $payload['seed']->save();
            SendPatchToDisk::dispatch($payload['seed']);

            $return_payload = Arr::except($payload, [
                'seed',
                'spoiler.meta.crystals_ganon',
                'spoiler.meta.crystals_tower',
            ]);

            if ($payload['spoiler']['meta']['tournament'] ?? false) {
                switch ($payload['spoiler']['meta']['spoilers']) {
                    case "on":
                    case "generate":
                        $return_payload = Arr::except($return_payload, [
                            'spoiler.playthrough',
                        ]);
                        break;
                    case "mystery":
                        $return_payload['spoiler'] = Arr::only($return_payload['spoiler'], ['meta']);
                        $return_payload['spoiler']['meta'] = Arr::only($return_payload['spoiler']['meta'], [
                            'name',
                            'notes',
                            'logic',
                            'build',
                            'tournament',
                            'spoilers',
                            'size',
                            'special',
                            'allow_quickswap'
                        ]);
                        break;
                    case "off":
                    default:
                        $return_payload['spoiler'] = Arr::except(Arr::only($return_payload['spoiler'], [
                            'meta',
                        ]), ['meta.seed']);
                }
            }

            $cached_payload = $return_payload;
            if ($payload['spoiler']['meta']['spoilers'] === 'generate') {
                // ensure that the cache doesn't have the spoiler, but the original return_payload still does
                $cached_payload['spoiler'] = Arr::except(Arr::only($return_payload['spoiler'], [
                    'meta',
                ]), ['meta.seed']);
            }
            $save_data = json_encode(Arr::except($cached_payload, [
                'current_rom_hash',
            ]));
            Cache::put('hash.' . $payload['hash'], $save_data, now()->addDays(7));

            return response()->json($return_payload);
        } catch (Exception $exception) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($exception);
            }

            return response($exception->getMessage(), 409);
        }
    }

    public function testGenerateSeed(CreateRandomizedGame $request)
    {
        try {
            return response()->json(Arr::except($this->prepSeed($request, false), ['patch', 'seed', 'hash']));
        } catch (Exception $exception) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($exception);
            }

            return response($exception->getMessage(), 409);
        }
    }

    protected function prepSeed(CreateRandomizedGame $request, bool $save = true)
    {
        $crystals_ganon = $request->input('crystals.ganon', '7');
        $crystals_ganon = $crystals_ganon === 'random' ? get_random_int(0, 7) : $crystals_ganon;
        $crystals_tower = $request->input('crystals.tower', '7');
        $crystals_tower = $crystals_tower === 'random' ? get_random_int(0, 7) : $crystals_tower;
        $logic = [
            'none' => 'NoGlitches',
            'overworld_glitches' => 'OverworldGlitches',
            'major_glitches' => 'MajorGlitches',
            'no_logic' => 'NoLogic',
        ][$request->input('glitches', 'none')];

        $spoilers = $request->input('spoilers', 'off');
        if (!$request->input('tournament', true)) {
            $spoilers = "on";
        } else if (!in_array($request->input('spoilers', 'off'), ["on", "off", "generate", "mystery"])) {
            $spoilers = "off";
        }

        // quick fix for CC and Basic
        if ($request->input('item.pool', 'normal') === 'crowd_control') {
            $request->merge(['item_placement' => 'advanced']);
        }

        $spoiler_meta = [];

        $purifier_settings = HTMLPurifier_Config::create(config("purifier.default"));
        $purifier_settings->loadArray(config("purifier.default"));
        $purifier = new HTMLPurifier($purifier_settings);
        if ($request->filled('name')) {
            $markdowned = Markdown::convertToHtml(substr($request->input('name'), 0, 100));
            $spoiler_meta['name'] = strip_tags($purifier->purify($markdowned));
        }
        if ($request->filled('notes')) {
            $markdowned = Markdown::convertToHtml(substr($request->input('notes'), 0, 300));
            $spoiler_meta['notes'] = $purifier->purify($markdowned);
        }

        $world = World::factory($request->input('mode', 'standard'), [
            'itemPlacement' => $request->input('item_placement', 'basic'),
            'dungeonItems' => $request->input('dungeon_items', 'standard'),
            'accessibility' => $request->input('accessibility', 'items'),
            'goal' => $request->input('goal', 'ganon'),
            'crystals.ganon' => $crystals_ganon,
            'crystals.tower' => $crystals_tower,
            'entrances' => $request->input('entrances', 'none'),
            'mode.weapons' => $request->input('weapons', 'randomized'),
            'tournament' => $request->input('tournament', false),
            'spoilers' => $spoilers,
            'allow_quickswap' => $request->input('allow_quickswap', true),
            'override_start_screen' => $request->input('override_start_screen', false),
            'spoil.Hints' => $request->input('hints', 'on'),
            'logic' => $logic,
            'item.pool' => $request->input('item.pool', 'normal'),
            'item.functionality' => $request->input('item.functionality', 'normal'),
            'enemizer.bossShuffle' => $request->input('enemizer.boss_shuffle', 'none'),
            'enemizer.enemyShuffle' => $request->input('enemizer.enemy_shuffle', 'none'),
            'enemizer.enemyDamage' => $request->input('enemizer.enemy_damage', 'default'),
            'enemizer.enemyHealth' => $request->input('enemizer.enemy_health', 'default'),
            'enemizer.potShuffle' => $request->input('enemizer.pot_shuffle', 'off'),
        ]);

        $rom = new Rom(config('alttp.base_rom'));
        $rom->applyPatchFile(Rom::getJsonPatchLocation());

        if ($world->config('entrances') !== 'none') {
            $rand = new EntranceRandomizer([$world]);
        } else {
            $rand = new Randomizer([$world]);
        }

        $rand->randomize();
        $world->writeToRom($rom, $save);

        // E.R. is responsible for verifying winnability of itself
        if ($world->config('entrances') === 'none') {
            $worlds = new WorldCollection($rand->getWorlds());

            if (!$worlds->isWinnable()) {
                throw new Exception('Game Unwinnable');
            }
        }

        $spoiler = $world->getSpoiler(array_merge($spoiler_meta, [
            'entry_crystals_ganon' => $request->input('crystals.ganon', '7'),
            'entry_crystals_tower' => $request->input('crystals.tower', '7'),
            'worlds' => 1,
        ]));

        if ($world->isEnemized()) {
            $patch = $rom->getWriteLog();
            $en = new Enemizer($world, $patch);
            $en->randomize();
            $en->writeToRom($rom);
        }

        if ($request->input('tournament', false)) {
            $rom->setTournamentType('standard');
            $rom->rummageTable();
        }
        $patch = $rom->getWriteLog();

        if ($save) {
            $world->updateSeedRecordPatch($patch);
        }

        return [
            'logic' => $world->config('logic'),
            'patch' => patch_merge_minify($patch),
            'spoiler' => $spoiler,
            'hash' => $world->getSeedRecord()->hash,
            'generated' => $world->getSeedRecord()->created_at ? $world->getSeedRecord()->created_at->toIso8601String() : now()->toIso8601String(),
            'seed' => $world->getSeedRecord(),
            'size' => $spoiler['meta']['size'] ?? 2,
            'current_rom_hash' => Rom::$HASH,
        ];
    }
}
