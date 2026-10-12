{{-- "Record result" and "Schedule" dialogs, shared by the Bracket, Matches and Overview tabs --}}
@php
    $rules = [
        'bestOf' => (int) ($tournament->best_of ?: 1),
        'games'  => (int) ($tournament->games_per_set ?: 6),
        'mtb'    => (int) $tournament->best_of > 1 && $tournament->final_set === 'match_tiebreak',
    ];
    $reopen = session('open_result');
    $oldSets = old('sets');
    $oldWalkover = old('walkover');
    $reopenError = $reopen ? $errors->first('result_' . $reopen) : null;
@endphp

<dialog id="result-dialog" class="m-auto rounded-2xl p-0 w-[min(94vw,30rem)] shadow-2xl backdrop:bg-gray-900/50">
    <form method="POST" id="result-form" class="p-5 grid gap-4">
        @csrf
        @method('PUT')

        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-green-700" id="result-round"></p>
                <h2 class="text-lg font-bold">Record result</h2>
            </div>
            <button type="button" data-close-dialog class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100" aria-label="Close"><x-icon name="x" class="w-5 h-5" /></button>
        </div>

        <p id="result-error" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800"></p>

        <div>
            <div class="grid grid-cols-[5.5rem_1fr_1fr_3.25rem] gap-2 items-end text-xs font-semibold text-gray-600 mb-1">
                <span></span>
                <span id="result-p1" class="text-center break-words"></span>
                <span id="result-p2" class="text-center break-words"></span>
                <span class="text-center" title="Losing side's tiebreak points">TB</span>
            </div>
            <div id="result-rows" class="grid gap-2"></div>
            <p class="mt-2 text-xs text-gray-500" id="result-help"></p>
        </div>

        <label class="grid gap-1 text-sm">
            <span class="text-gray-600">Or record a walkover (opponent did not play):</span>
            <select name="walkover" id="result-walkover" class="rounded-lg border border-gray-300 px-3 py-2">
                <option value="">No walkover</option>
                <option value="1" id="result-wo1"></option>
                <option value="2" id="result-wo2"></option>
            </select>
        </label>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
            <button type="button" data-close-dialog class="rounded-lg px-4 py-2.5 text-gray-700 hover:bg-gray-100 font-medium">Cancel</button>
            <button type="submit" data-loading="Saving..." class="rounded-lg bg-green-700 text-white font-semibold px-5 py-2.5 hover:bg-green-800">Save result</button>
        </div>
        <p class="text-xs text-gray-500 -mt-2">The winner is worked out from the score and moves on automatically.</p>
    </form>

    <form method="POST" id="clear-form" class="hidden px-5 pb-5 -mt-2"
          data-confirm="Clear this result?" data-confirm-body="The score is removed and the winner is taken back out of the next round." data-confirm-button="Clear result" data-confirm-tone="danger">
        @csrf
        @method('DELETE')
        <button class="text-sm text-red-700 hover:underline">Clear this result</button>
    </form>
</dialog>

<dialog id="schedule-dialog" class="m-auto rounded-2xl p-0 w-[min(94vw,26rem)] shadow-2xl backdrop:bg-gray-900/50">
    <form method="POST" id="schedule-form" class="p-5 grid gap-4">
        @csrf
        @method('PATCH')
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-green-700" id="schedule-round"></p>
                <h2 class="text-lg font-bold">Schedule match</h2>
            </div>
            <button type="button" data-close-dialog class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100" aria-label="Close"><x-icon name="x" class="w-5 h-5" /></button>
        </div>
        <label class="grid gap-1 text-sm">
            <span class="font-medium">Date &amp; time</span>
            <input type="datetime-local" name="scheduled_at" id="schedule-when" class="rounded-lg border border-gray-300 px-3 py-2">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-medium">Court</span>
            <input type="text" name="court" id="schedule-court" maxlength="60" placeholder="e.g. Court 2" class="rounded-lg border border-gray-300 px-3 py-2">
        </label>
        <label class="grid gap-1 text-sm" id="schedule-status-wrap">
            <span class="font-medium">Status</span>
            <select name="status" id="schedule-status" class="rounded-lg border border-gray-300 px-3 py-2">
                <option value="scheduled">Scheduled</option>
                <option value="in_progress">In Progress</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </label>
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <button type="button" data-close-dialog class="rounded-lg px-4 py-2.5 text-gray-700 hover:bg-gray-100 font-medium">Cancel</button>
            <button type="submit" data-loading="Saving..." class="rounded-lg bg-gray-900 text-white font-semibold px-5 py-2.5 hover:bg-gray-800">Save</button>
        </div>
    </form>
</dialog>

<script>
    (function () {
        var rules = @json($rules);
        var reopen = @json($reopen);
        var oldSets = @json($oldSets);
        var oldWalkover = @json($oldWalkover);
        var reopenError = @json($reopenError);

        var dialog = document.getElementById('result-dialog');
        var rows = document.getElementById('result-rows');

        function input(name, value, max, extra) {
            return '<input type="number" inputmode="numeric" min="0" max="' + max + '" name="' + name + '" value="' + (value === null || value === undefined ? '' : value) + '" class="w-full rounded-lg border border-gray-300 px-2 py-2 text-center text-base ' + (extra || '') + '">';
        }

        function openResult(data, sets, walkover, error) {
            document.getElementById('result-form').action = data.action;
            document.getElementById('result-round').textContent = data.code + ' \u00b7 ' + data.round;
            document.getElementById('result-p1').textContent = data.p1;
            document.getElementById('result-p2').textContent = data.p2;
            document.getElementById('result-wo1').textContent = data.p1 + ' wins by walkover';
            document.getElementById('result-wo2').textContent = data.p2 + ' wins by walkover';
            document.getElementById('result-walkover').value = walkover || '';

            var html = '';
            for (var i = 0; i < rules.bestOf; i++) {
                var s = (sets && sets[i]) || {};
                var isMtb = rules.mtb && i === rules.bestOf - 1;
                html += '<div class="grid grid-cols-[5.5rem_1fr_1fr_3.25rem] gap-2 items-center">'
                    + '<span class="text-sm text-gray-600">' + (isMtb ? 'Match tiebreak' : 'Set ' + (i + 1)) + '</span>'
                    + input('sets[' + i + '][p1]', s.p1, isMtb ? 99 : rules.games + 1)
                    + input('sets[' + i + '][p2]', s.p2, isMtb ? 99 : rules.games + 1)
                    + (isMtb ? '<span></span>' : input('sets[' + i + '][tb]', s.tb, 99, 'bg-gray-50 text-sm'))
                    + '</div>';
            }
            rows.innerHTML = html;

            var help = 'Enter the games each side won.';
            if (rules.bestOf > 1) help += ' Leave unused sets empty.';
            help += ' TB = losing side\u2019s tiebreak points, only for ' + (rules.games + 1) + '-' + rules.games + ' sets.';
            if (rules.mtb) help += ' Match tiebreak: enter the points, e.g. 10 and 8.';
            document.getElementById('result-help').textContent = help;

            var err = document.getElementById('result-error');
            err.textContent = error || '';
            err.classList.toggle('hidden', !error);

            var clear = document.getElementById('clear-form');
            clear.action = data.clear;
            clear.classList.toggle('hidden', !data.decided);

            dialog.showModal();
            var first = rows.querySelector('input');
            if (first && !data.decided) first.focus();
        }

        function openSchedule(data) {
            var form = document.getElementById('schedule-form');
            form.action = data.schedule;
            document.getElementById('schedule-round').textContent = data.code + ' \u00b7 ' + (data.p1 || '?') + ' vs ' + (data.p2 || '?');
            document.getElementById('schedule-when').value = data.when || '';
            document.getElementById('schedule-court').value = data.court || '';
            var status = document.getElementById('schedule-status');
            status.querySelector('option[value="cancelled"]').hidden = !data.canCancel;
            status.value = data.status === 'completed' ? 'scheduled' : data.status;
            document.getElementById('schedule-status-wrap').style.display = data.status === 'completed' ? 'none' : '';
            document.getElementById('schedule-dialog').showModal();
        }

        document.addEventListener('click', function (e) {
            var r = e.target.closest('[data-result]');
            if (r) { openResult(JSON.parse(r.dataset.result), JSON.parse(r.dataset.result).sets, JSON.parse(r.dataset.result).walkover); return; }
            var s = e.target.closest('[data-schedule]');
            if (s) { openSchedule(JSON.parse(s.dataset.schedule)); return; }
            var c = e.target.closest('[data-close-dialog]');
            if (c) { c.closest('dialog').close(); }
        });

        // After a validation error, reopen the dialog with what was typed
        if (reopen) {
            var btn = document.querySelector('[data-result*="\\"id\\":' + reopen + ',"]');
            if (btn) {
                var data = JSON.parse(btn.dataset.result);
                var typed = oldSets ? Object.keys(oldSets).map(function (k) { return oldSets[k]; }) : data.sets;
                openResult(data, typed, oldWalkover, reopenError);
            }
        }
    })();
</script>