@extends('layouts.app')
@section('title', $endpoint->name)

@php
    $params = collect($endpoint->parameters ?? []);
    $featureLabels = config('paystack.feature_labels');
    $executeLabel = match ($endpoint->workflow) {
        'bank_table' => 'Fetch Banks',
        default => 'Execute Request',
    };
@endphp

@section('content')
<div x-data="endpointConsole" data-favorite="{{ $isFavorite ? '1' : '0' }}" class="space-y-4">

    {{-- Header --}}
    <section class="card" aria-labelledby="endpoint-title">
        <div class="card-body space-y-3">
            <nav class="text-xs text-ink-500" aria-label="Breadcrumb">
                <a href="{{ route('explorer.index') }}" class="hover:text-ink-300">Explorer</a>
                <span aria-hidden="true">/</span>
                <span>{{ $endpoint->category->name }}</span>
            </nav>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 id="endpoint-title" class="text-xl font-semibold text-ink-100 sm:text-2xl">{{ $endpoint->name }}</h1>
                    <div class="mt-2 flex min-w-0 items-center gap-2">
                        <x-method :method="$endpoint->method" class="text-xs" />
                        <code class="truncate font-mono text-sm text-ink-200">{{ $endpoint->path }}</code>
                        <button type="button" class="btn btn-ghost btn-sm" @click="copy(@js($endpoint->method.' '.config('paystack.base_url').$endpoint->path), 'Endpoint copied')" aria-label="Copy method and URL">⧉</button>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn btn-secondary" @click="toggleFavorite()" :aria-pressed="isFavorite"
                            :class="isFavorite ? 'text-amber-300 border-amber-500/50' : ''">
                        <span x-text="isFavorite ? '★' : '☆'" aria-hidden="true"></span>
                        <span x-text="isFavorite ? 'Favorited' : 'Favorite'"></span>
                    </button>
                    @if ($endpoint->docs_url)
                        <a href="{{ $endpoint->docs_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">Official docs ↗</a>
                    @endif
                </div>
            </div>
            @if ($endpoint->description)
                <p class="max-w-3xl text-sm text-ink-300">{{ $endpoint->description }}</p>
            @endif
            <div class="flex flex-wrap gap-1.5">
                <span class="badge badge-neutral">{{ $endpoint->category->name }}</span>
                @if ($endpoint->auth === 'public')<span class="badge badge-info" title="Paystack authorizes this endpoint with the public key">Public key auth</span>@endif
                @if ($endpoint->is_dangerous)<span class="badge badge-danger" title="Moves money or makes an irreversible change">Money movement / irreversible</span>@endif
                @if ($endpoint->pagination)<span class="badge badge-neutral">Paginated ({{ $endpoint->pagination }})</span>@endif
                @if ($endpoint->workflow)<span class="badge badge-info">{{ \App\Models\Endpoint::WORKFLOWS[$endpoint->workflow] ?? $endpoint->workflow }}</span>@endif
                @if (! $endpoint->is_documented)<span class="badge badge-warning">Not on API reference pages</span>@endif
                @if ($endpoint->source === 'manual')<span class="badge badge-neutral" title="Documented on the API reference but missing from the OpenAPI spec">Reference-only</span>@endif
                @foreach ($endpoint->required_features ?? [] as $feature)
                    <span class="badge badge-neutral">{{ $featureLabels[$feature] ?? $feature }}</span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Notices --}}
    @if ($problems)
        <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-200" role="alert">
            <strong class="block">Not available for {{ $context->country->name }} / {{ $context->currency->code }}</strong>
            <ul class="mt-1 list-inside list-disc">@foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul>
            <p class="mt-1 text-amber-300/80">Switch country or currency in the sidebar to use this endpoint.</p>
        </div>
    @endif
    @if ($endpoint->notes)
        <div class="rounded-lg border border-ink-600 bg-ink-850 px-4 py-2 text-sm text-ink-300">{{ $endpoint->notes }}</div>
    @endif
    @if ($prefillSource)
        <div class="rounded-lg border border-brand-500/40 bg-brand-500/10 px-4 py-2 text-sm text-brand-400" role="status">
            {{ $prefillSource }}. Nothing has been sent. Review the values and execute when ready.
            @if ($maskedFields) <br>Sensitive fields were not stored and must be re-entered: <span class="font-mono">{{ implode(', ', $maskedFields) }}</span>. @endif
        </div>
    @endif
    @if ($context->isLive())
        <div class="rounded-lg border border-red-500 bg-red-600/15 px-4 py-2 text-sm font-semibold text-red-300" role="alert">
            LIVE MODE: this request will use production credentials for {{ $context->country->name }}. You will be asked to confirm.
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-2">
        {{-- ============================== REQUEST ============================== --}}
        <section class="card min-w-0" aria-labelledby="request-heading">
            <div class="card-header flex-wrap">
                <h2 id="request-heading" class="sr-only">Request</h2>
                <div class="flex border-b border-transparent" role="tablist" aria-label="Request panel">
                    <button type="button" role="tab" class="tab" :class="requestTab === 'form' && 'tab-active'" :aria-selected="requestTab === 'form'" @click="requestTab = 'form'">Request</button>
                    <button type="button" role="tab" class="tab" :class="requestTab === 'docs' && 'tab-active'" :aria-selected="requestTab === 'docs'" @click="requestTab = 'docs'">Documentation</button>
                </div>
                <div class="flex items-center gap-1 text-xs text-ink-400">
                    <span>{{ $context->country->flag }} {{ $context->country->code }}</span>·<span class="font-mono">{{ $context->currency->code }}</span>
                    <x-env-badge :environment="$context->environment->value" />
                </div>
            </div>

            {{-- Request form --}}
            <form x-ref="form" x-show="requestTab === 'form'" @submit.prevent="execute()" class="card-body space-y-5" novalidate>
                <div class="rounded-lg border border-ink-700 bg-ink-950 px-3 py-2">
                    <div class="text-[11px] uppercase tracking-wide text-ink-500">Request URL</div>
                    <div class="mt-0.5 break-all font-mono text-xs text-ink-200"><span class="method mr-1" :class="`method-${def.method}`" x-text="def.method"></span><span x-text="urlPreview"></span></div>
                </div>

                @if ($params->isEmpty())
                    <p class="text-sm text-ink-400">This endpoint takes no parameters.</p>
                @endif

                <template x-for="group in [{ key: 'required', title: 'Required parameters', list: requiredParams }, { key: 'optional', title: 'Optional parameters', list: optionalParams }]" :key="group.key">
                    <fieldset x-show="group.list.length > 0" class="space-y-4">
                        <legend class="flex w-full items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider" :class="group.key === 'required' ? 'text-red-300' : 'text-ink-400'"
                                  x-text="`${group.title} (${group.list.length})`"></span>
                            <button type="button" x-show="group.key === 'optional'" class="btn btn-ghost btn-sm" @click="showOptional = !showOptional" :aria-expanded="showOptional"
                                    x-text="showOptional ? 'Hide' : 'Show'"></button>
                        </legend>

                        <div class="space-y-4" x-show="group.key === 'required' || showOptional">
                            <template x-for="p in group.list" :key="p.name">
                                <div>
                                    <label :for="`field-${p.name}`" class="label flex flex-wrap items-center gap-x-2">
                                        <span class="font-mono text-ink-100" x-text="p.name"></span>
                                        <span x-show="p.required" class="text-red-400" aria-hidden="true">*</span>
                                        <span x-show="p.required" class="sr-only">(required)</span>
                                        <span class="font-normal text-ink-500" x-text="`${p.type}${p.items?.type ? '<' + p.items.type + '>' : ''} · ${p.in}`"></span>
                                        <span x-show="p.root" class="badge badge-warning normal-case">entire request body (JSON array)</span>
                                        <span x-show="p.binding" class="badge badge-info normal-case" x-text="p.binding ? `bound to ${p.binding.replace('_', ' ')}` : ''"></span>
                                    </label>

                                    {{-- text-like inputs --}}
                                    <template x-if="['text', 'email', 'url', 'number', 'date', 'datetime'].includes(p.ui)">
                                        <input :id="`field-${p.name}`" :type="inputType(p)" x-model="values[p.name]" class="input font-mono"
                                               :class="errors[p.name] ? 'input-error' : ''" :step="p.type === 'integer' ? 1 : 'any'"
                                               :required="p.required" :aria-required="p.required" :aria-invalid="Boolean(errors[p.name])"
                                               :aria-describedby="`help-${p.name}`" :placeholder="p.example !== undefined && typeof p.example !== 'object' ? String(p.example) : ''"
                                               :autocomplete="p.sensitive ? 'off' : 'on'">
                                    </template>

                                    {{-- enum --}}
                                    <template x-if="p.ui === 'select'">
                                        <select :id="`field-${p.name}`" x-model="values[p.name]" class="input" :class="errors[p.name] ? 'input-error' : ''"
                                                :aria-invalid="Boolean(errors[p.name])" :aria-describedby="`help-${p.name}`">
                                            <option value="" x-text="p.required ? 'Select…' : '— not set —'"></option>
                                            <template x-for="opt in allowed(p)" :key="opt">
                                                <option :value="String(opt)" x-text="optionLabel(p, opt)" :selected="values[p.name] === String(opt)"></option>
                                            </template>
                                        </select>
                                    </template>

                                    {{-- boolean: tri-state so "not sent" is distinguishable from false --}}
                                    <template x-if="p.ui === 'toggle'">
                                        <div class="inline-flex overflow-hidden rounded-lg border border-ink-600" role="radiogroup" :aria-labelledby="`field-${p.name}`" :id="`field-${p.name}`">
                                            <template x-for="opt in [['', 'Not set'], ['true', 'true'], ['false', 'false']]" :key="opt[0]">
                                                <button type="button" role="radio" class="px-3 py-1.5 font-mono text-xs"
                                                        :aria-checked="values[p.name] === opt[0]"
                                                        :class="values[p.name] === opt[0] ? 'bg-brand-500 text-ink-950' : 'bg-ink-850 text-ink-300 hover:bg-ink-800'"
                                                        @click="values[p.name] = opt[0]" x-text="opt[1]"></button>
                                            </template>
                                        </div>
                                    </template>

                                    {{-- array with enum --}}
                                    <template x-if="p.ui === 'multiselect'">
                                        <div class="flex flex-wrap gap-2" :id="`field-${p.name}`" role="group" :aria-describedby="`help-${p.name}`">
                                            <template x-for="opt in allowed(p)" :key="opt">
                                                <label class="flex cursor-pointer items-center gap-1.5 rounded-md border px-2 py-1 text-xs"
                                                       :class="values[p.name].includes(opt) ? 'border-brand-500 bg-brand-500/15 text-ink-100' : 'border-ink-600 text-ink-300'">
                                                    <input type="checkbox" class="sr-only" :checked="values[p.name].includes(opt)" @change="toggleMulti(p, opt)">
                                                    <span x-text="optionLabel(p, opt)"></span>
                                                    <span class="font-mono text-ink-500" x-text="opt"></span>
                                                </label>
                                            </template>
                                            <p x-show="allowed(p).length === 0" class="help">No values available for the selected market.</p>
                                        </div>
                                    </template>

                                    {{-- array of scalars --}}
                                    <template x-if="p.ui === 'repeatable'">
                                        <div class="space-y-2" :id="`field-${p.name}`">
                                            <template x-for="(item, index) in values[p.name]" :key="index">
                                                <div class="flex gap-2">
                                                    <input class="input font-mono" x-model="values[p.name][index]" :aria-label="`${p.name} item ${index + 1}`"
                                                           :type="['integer', 'number'].includes(p.items?.type) ? 'number' : 'text'">
                                                    <button type="button" class="btn btn-ghost" @click="removeRepeat(p, index)" :aria-label="`Remove ${p.name} item ${index + 1}`">✕</button>
                                                </div>
                                            </template>
                                            <button type="button" class="btn btn-secondary btn-sm" @click="addRepeat(p)">+ Add item</button>
                                        </div>
                                    </template>

                                    {{-- JSON editor --}}
                                    <template x-if="p.ui === 'json'">
                                        <div class="space-y-1.5">
                                            <div class="json-editor rounded-lg border bg-ink-950" :class="jsonError(p.name) ? 'border-red-500/70' : 'border-ink-600'">
                                                <pre aria-hidden="true" x-html="jsonHighlight(p.name)"></pre>
                                                <textarea :id="`field-${p.name}`" x-model="values[p.name]" spellcheck="false" rows="6"
                                                          @scroll="syncScroll($event)" :aria-invalid="Boolean(jsonError(p.name) || errors[p.name])"
                                                          :aria-describedby="`help-${p.name}`" :placeholder="p.type === 'array' ? '[ ]' : '{ }'"
                                                          @keydown.tab.prevent="$event.target.setRangeText('  ', $event.target.selectionStart, $event.target.selectionEnd, 'end'); values[p.name] = $event.target.value"></textarea>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <button type="button" class="btn btn-ghost btn-sm" @click="jsonFormat(p.name)">Format</button>
                                                <button type="button" class="btn btn-ghost btn-sm" @click="jsonMinify(p.name)">Minify</button>
                                                <button type="button" class="btn btn-ghost btn-sm" @click="jsonTemplate(p)">Insert template</button>
                                                <button type="button" class="btn btn-ghost btn-sm" x-show="hasExample(p)" @click="jsonExample(p)">Insert documented example</button>
                                                <span class="text-xs" :class="jsonError(p.name) ? 'text-red-400' : 'text-emerald-400'"
                                                      x-text="String(values[p.name]).trim() === '' ? '' : (jsonError(p.name) ? `Invalid JSON: ${jsonError(p.name)}` : 'Valid JSON')"></span>
                                                <span x-show="p.type === 'string'" class="text-xs text-ink-500">(sent as a JSON string)</span>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- file upload (multipart/form-data) --}}
                                    <template x-if="p.ui === 'file'">
                                        <input :id="`field-${p.name}`" type="file" class="input file:mr-3 file:rounded file:border-0 file:bg-ink-700 file:px-2 file:py-1 file:text-ink-100" @change="onFile(p, $event)">
                                    </template>

                                    <div :id="`help-${p.name}`">
                                        <p class="help" x-show="p.description" x-text="p.description"></p>
                                        <p class="help font-mono text-brand-400" x-show="amountHint(p)" x-text="amountHint(p)"></p>
                                        <p class="help" x-show="p.ui === 'select' && p.binding && allowed(p).length < (p.enum?.length ?? 0)" x-text="`Options limited to what is available for ${config.context.country_name}.`"></p>
                                    </div>
                                    <template x-for="message in (errors[p.name] ?? [])" :key="message">
                                        <p class="error-text" role="alert" x-text="message"></p>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </fieldset>
                </template>

                <div x-show="failure && !result" x-cloak class="rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-300" role="alert">
                    <span x-text="failure?.message"></span>
                </div>

                <div class="sticky bottom-0 -mx-4 -mb-4 flex flex-wrap items-center gap-2 border-t border-ink-700 bg-ink-900/95 px-4 py-3 backdrop-blur">
                    @can('explorer.execute')
                        <button type="submit" class="btn {{ $context->isLive() ? 'btn-live' : 'btn-primary' }}" :disabled="loading || {{ $problems ? 'true' : 'false' }}">
                            <svg x-show="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 01-10 10" stroke="currentColor" stroke-width="3"/></svg>
                            <span x-text="loading ? 'Sending…' : @js(($context->isLive() ? 'Execute LIVE: ' : '').$executeLabel)">{{ $executeLabel }}</span>
                        </button>
                    @else
                        <span class="badge badge-neutral">Read-only role: execution disabled</span>
                    @endcan
                    <button type="button" class="btn btn-secondary" @click="reset()">Reset</button>
                    <button type="button" class="btn btn-secondary" @click="copy(curlPreview, 'cURL copied')">Copy cURL</button>
                    <div class="relative" @click.outside="saveOpen = false">
                        <button type="button" class="btn btn-ghost" @click="saveOpen = !saveOpen" :aria-expanded="saveOpen">Save…</button>
                        <div x-cloak x-show="saveOpen" class="absolute bottom-full left-0 mb-2 w-72 rounded-lg border border-ink-700 bg-ink-850 p-3 shadow-xl">
                            <label for="save-name" class="label">Configuration name</label>
                            <div class="flex gap-2">
                                <input id="save-name" x-model="saveName" class="input" maxlength="120" @keydown.enter.prevent="saveConfiguration()">
                                <button type="button" class="btn btn-primary" @click="saveConfiguration()">Save</button>
                            </div>
                            <p class="help">PINs, OTPs and other sensitive values are never saved.</p>
                        </div>
                    </div>
                    <span class="ml-auto hidden text-xs text-ink-500 sm:inline">Press <span class="kbd">Enter</span> in a field to execute</span>
                </div>
            </form>

            {{-- Documentation --}}
            <div x-cloak x-show="requestTab === 'docs'" class="card-body space-y-5 text-sm">
                @include('explorer.partials.docs', ['endpoint' => $endpoint, 'params' => $params, 'featureLabels' => $featureLabels])
            </div>
        </section>

        {{-- ============================== RESPONSE ============================== --}}
        <section x-ref="response" class="card min-w-0 scroll-mt-20" aria-labelledby="response-heading" aria-live="polite">
            <div class="card-header flex-wrap">
                <h2 id="response-heading" class="font-semibold text-ink-100">Response</h2>
                <template x-if="response">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="badge text-xs" :class="statusClass" x-text="response.status ? `${response.status} ${response.status_text}` : 'No response'"></span>
                        <span class="font-mono text-ink-300" x-text="`${response.duration_ms} ms`"></span>
                        <span class="font-mono text-ink-500" x-text="`${(response.size_bytes / 1024).toFixed(1)} KB`"></span>
                        <span x-show="response.is_mock" class="badge badge-info">Mock response</span>
                        <a x-show="result.history_id" :href="`{{ url('api-explorer/history') }}/${result.history_id}`" class="text-brand-400 hover:underline">History entry</a>
                    </div>
                </template>
            </div>

            <div class="card-body space-y-4">
                <div x-show="!result && !loading && !failure" class="grid place-items-center py-16 text-center text-sm text-ink-500">
                    <div>
                        <div class="text-3xl" aria-hidden="true">⇢</div>
                        <p class="mt-2">Fill in the request and press <strong class="text-ink-300">{{ $executeLabel }}</strong>.</p>
                        <p class="mt-1 text-xs">The request is sent by the Laravel server; your secret key never reaches the browser.</p>
                    </div>
                </div>

                <div x-show="loading" class="grid place-items-center py-16 text-sm text-ink-400" x-cloak>Waiting for Paystack…</div>

                {{-- Explorer-side failure (validation, unavailable, credentials, rate limit...) --}}
                <template x-if="failure && failure.code !== 'client_validation'">
                    <div class="rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm" role="alert">
                        <div class="flex items-center gap-2">
                            <span class="badge badge-danger" x-text="failure.status ? `HTTP ${failure.status}` : 'Error'"></span>
                            <span class="font-mono text-xs text-red-300/80" x-text="failure.code ?? ''"></span>
                        </div>
                        <p class="mt-2 text-red-200" x-text="failure.message"></p>
                        <ul class="mt-1 list-inside list-disc text-red-300" x-show="failure.details?.length">
                            <template x-for="d in failure.details" :key="d"><li x-text="d"></li></template>
                        </ul>
                        <p class="mt-2 text-xs text-ink-400">This error came from the explorer; the request was not sent to Paystack.</p>
                    </div>
                </template>

                <template x-if="response && !loading">
                    <div class="space-y-4">
                        {{-- Paystack message & documented meaning of the status --}}
                        <div class="rounded-lg border p-3 text-sm" :class="response.ok ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-amber-500/40 bg-amber-500/10'">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold" :class="response.ok ? 'text-emerald-300' : 'text-amber-200'" x-text="response.paystack?.message ?? (response.transport_error ?? response.status_text)"></span>
                                <span x-show="response.paystack?.code" class="badge badge-neutral normal-case" x-text="response.paystack?.code"></span>
                                <span x-show="response.paystack?.type" class="badge badge-neutral normal-case" x-text="response.paystack?.type"></span>
                            </div>
                            <p x-show="response.paystack?.next_step" class="mt-1 text-ink-300"><span class="text-ink-500">Next step:</span> <span x-text="response.paystack?.next_step"></span></p>
                            <template x-if="response.hint && !response.ok">
                                <p class="mt-2 text-xs text-ink-400"><span class="font-semibold text-ink-300" x-text="response.hint.title"></span>: <span x-text="response.hint.text"></span> <span class="italic" x-text="`(${response.hint.source})`"></span></p>
                            </template>
                        </div>

                        <template x-if="result.warnings?.length">
                            <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-200">
                                <template x-for="w in result.warnings" :key="w"><p x-text="w"></p></template>
                            </div>
                        </template>

                        {{-- Payment flow actions --}}
                        <template x-if="response.links.length || response.reference">
                            <div class="rounded-lg border border-brand-500/40 bg-brand-500/10 p-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-brand-400">Payment flow</div>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <template x-for="link in response.links" :key="link.url">
                                        <a :href="link.url" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                                            <span x-text="link.label"></span> ↗
                                        </a>
                                    </template>
                                    <template x-if="verifyUrl">
                                        <a :href="verifyUrl" class="btn btn-secondary">{{ __('Verify') }}: <span x-text="config.verify_name"></span></a>
                                    </template>
                                </div>
                                <div class="mt-2 space-y-1 text-xs text-ink-300">
                                    <p x-show="response.links.length">Opens on <span class="font-mono" x-text="response.links[0]?.host"></span> in a new tab. After payment Paystack redirects to the callback URL, which verifies the transaction.</p>
                                    <p x-show="response.reference">Reference: <span class="font-mono text-ink-100" x-text="response.reference"></span>
                                        <button type="button" class="btn btn-ghost btn-sm" @click="copy(response.reference, 'Reference copied')">Copy</button></p>
                                </div>
                            </div>
                        </template>

                        {{-- Tabs --}}
                        <div class="flex flex-wrap border-b border-ink-700" role="tablist" aria-label="Response views">
                            <template x-for="tab in [
                                ['summary', 'Summary', Boolean(transaction) && def.workflow === 'transaction_result'],
                                ['banks', 'Banks', bankRows.length > 0],
                                ['table', 'Table', dataRows.length > 0 && def.workflow !== 'bank_table'],
                                ['pretty', 'Pretty', response.is_json],
                                ['raw', 'Raw', true],
                                ['payload', 'Payload', true],
                                ['headers', 'Headers', true],
                                ['request', 'Request', true],
                                ['curl', 'cURL', true],
                            ].filter(t => t[2])" :key="tab[0]">
                                <button type="button" role="tab" class="tab" :class="responseTab === tab[0] && 'tab-active'" :aria-selected="responseTab === tab[0]"
                                        @click="responseTab = tab[0]" x-text="tab[1]"></button>
                            </template>
                            <div class="ml-auto flex items-center gap-1 pb-1">
                                <button type="button" class="btn btn-ghost btn-sm" @click="copyResponse()" x-text="showingPayload ? 'Copy payload' : 'Copy response'">Copy response</button>
                                <button type="button" class="btn btn-ghost btn-sm" @click="downloadResponse()">Download</button>
                            </div>
                        </div>

                        {{-- Summary (transaction result) --}}
                        <div x-show="responseTab === 'summary'" x-cloak>
                            <template x-if="transaction">
                                <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                                    <div class="col-span-2 sm:col-span-3 flex items-center gap-2">
                                        <span class="badge text-sm" :class="transaction.status === 'success' ? 'badge-success' : (['failed', 'abandoned', 'reversed'].includes(transaction.status) ? 'badge-danger' : 'badge-warning')" x-text="transaction.status"></span>
                                        <span class="text-ink-300" x-text="transaction.gateway_response ?? ''"></span>
                                    </div>
                                    <div><dt class="text-xs text-ink-500">Amount</dt><dd class="font-mono text-ink-100" x-text="formatAmount(transaction.amount, transaction.currency)"></dd></div>
                                    <div><dt class="text-xs text-ink-500">Reference</dt><dd class="break-all font-mono text-ink-100" x-text="transaction.reference ?? '—'"></dd></div>
                                    <div><dt class="text-xs text-ink-500">Channel</dt><dd class="text-ink-100" x-text="transaction.channel ?? '—'"></dd></div>
                                    <div><dt class="text-xs text-ink-500">Paid at</dt><dd class="text-ink-100" x-text="transaction.paid_at ?? transaction.paidAt ?? '—'"></dd></div>
                                    <div><dt class="text-xs text-ink-500">Customer</dt><dd class="break-all text-ink-100" x-text="transaction.customer?.email ?? '—'"></dd></div>
                                    <div><dt class="text-xs text-ink-500">Card</dt><dd class="text-ink-100" x-text="transaction.authorization?.last4 ? `${transaction.authorization.brand ?? ''} •••• ${transaction.authorization.last4}` : '—'"></dd></div>
                                    <div class="col-span-2 sm:col-span-3" x-show="transaction.authorization?.authorization_code">
                                        <dt class="text-xs text-ink-500">Authorization code <span x-show="transaction.authorization?.reusable">(reusable)</span></dt>
                                        <dd class="font-mono text-ink-100" x-text="transaction.authorization?.authorization_code"></dd>
                                    </div>
                                </dl>
                            </template>
                        </div>

                        {{-- Banks --}}
                        <div x-show="responseTab === 'banks'" x-cloak class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <label for="bank-search" class="sr-only">Search banks</label>
                                <input id="bank-search" type="search" x-model="bankSearch" @input="bankPage = 1" class="input max-w-xs" placeholder="Search name, code, slug…">
                                <span class="text-xs text-ink-400" x-text="`${filteredBanks.length} of ${bankRows.length} banks`"></span>
                                <label class="ml-auto flex items-center gap-1 text-xs text-ink-400">Rows
                                    <select x-model.number="bankPerPage" @change="bankPage = 1" class="input w-20 py-1 text-xs">
                                        <option>10</option><option>20</option><option>50</option><option>100</option>
                                    </select>
                                </label>
                            </div>
                            <div class="overflow-x-auto rounded-lg border border-ink-700">
                                <table class="table">
                                    <thead><tr><th>Bank name</th><th>Code</th><th class="hidden md:table-cell">Long code</th><th class="hidden lg:table-cell">Country</th><th>Currency</th><th class="hidden sm:table-cell">Type</th><th>Active</th><th class="hidden lg:table-cell">ID</th></tr></thead>
                                    <tbody>
                                    <template x-for="bank in pagedBanks" :key="bank.id ?? bank.code">
                                        <tr>
                                            <td>
                                                <span class="text-ink-100" x-text="bank.name"></span>
                                                <button type="button" class="ml-1 text-xs text-ink-500 hover:text-brand-400" @click="copy(bank.name, 'Bank name copied')" :aria-label="`Copy name ${bank.name}`">⧉</button>
                                            </td>
                                            <td class="whitespace-nowrap font-mono">
                                                <span x-text="bank.code"></span>
                                                <button type="button" class="ml-1 text-xs text-ink-500 hover:text-brand-400" @click="copy(bank.code, 'Bank code copied')" :aria-label="`Copy code ${bank.code}`">⧉</button>
                                            </td>
                                            <td class="hidden font-mono text-xs md:table-cell" x-text="bank.longcode || '—'"></td>
                                            <td class="hidden lg:table-cell" x-text="bank.country ?? '—'"></td>
                                            <td class="font-mono" x-text="bank.currency ?? '—'"></td>
                                            <td class="hidden font-mono text-xs sm:table-cell" x-text="bank.type ?? '—'"></td>
                                            <td><span class="badge" :class="bank.active ? 'badge-success' : 'badge-neutral'" x-text="bank.active ? 'Yes' : 'No'"></span></td>
                                            <td class="hidden font-mono text-xs text-ink-400 lg:table-cell" x-text="bank.id"></td>
                                        </tr>
                                    </template>
                                    </tbody>
                                </table>
                            </div>
                            <div class="flex items-center justify-end gap-2 text-xs">
                                <button type="button" class="btn btn-secondary btn-sm" :disabled="bankPage <= 1" @click="bankPage--">Previous</button>
                                <span class="text-ink-400" x-text="`Page ${bankPage} of ${bankPageCount}`"></span>
                                <button type="button" class="btn btn-secondary btn-sm" :disabled="bankPage >= bankPageCount" @click="bankPage++">Next</button>
                            </div>
                        </div>

                        {{-- Generic table --}}
                        <div x-show="responseTab === 'table'" x-cloak class="space-y-3">
                            <div class="flex items-center gap-2">
                                <input type="search" x-model="tableSearch" @input="tablePage = 1" class="input max-w-xs" placeholder="Filter rows…" aria-label="Filter rows">
                                <span class="text-xs text-ink-400" x-text="`${filteredTableRows.length} rows`"></span>
                            </div>
                            <div class="overflow-x-auto rounded-lg border border-ink-700">
                                <table class="table">
                                    <thead><tr><template x-for="col in tableColumns" :key="col"><th x-text="col"></th></template></tr></thead>
                                    <tbody>
                                    <template x-for="(row, i) in pagedTableRows" :key="i">
                                        <tr><template x-for="col in tableColumns" :key="col"><td class="max-w-56 truncate font-mono text-xs" x-text="cell(row[col])" :title="cell(row[col])"></td></template></tr>
                                    </template>
                                    </tbody>
                                </table>
                            </div>
                            <div class="flex items-center justify-end gap-2 text-xs" x-show="tablePageCount > 1">
                                <button type="button" class="btn btn-secondary btn-sm" :disabled="tablePage <= 1" @click="tablePage--">Previous</button>
                                <span class="text-ink-400" x-text="`Page ${tablePage} of ${tablePageCount}`"></span>
                                <button type="button" class="btn btn-secondary btn-sm" :disabled="tablePage >= tablePageCount" @click="tablePage++">Next</button>
                            </div>
                        </div>

                        {{-- Pretty tree --}}
                        <div x-show="responseTab === 'pretty'" x-cloak class="space-y-2">
                            <div class="flex gap-1">
                                <button type="button" class="btn btn-ghost btn-sm" @click="expandAll(true)">Expand all</button>
                                <button type="button" class="btn btn-ghost btn-sm" @click="expandAll(false)">Collapse all</button>
                            </div>
                            <div x-ref="tree" class="json-tree code-block max-h-[36rem]" x-html="treeHtml"></div>
                        </div>

                        {{-- Raw --}}
                        <pre x-show="responseTab === 'raw'" x-cloak class="code-block max-h-[36rem]" x-html="rawHtml"></pre>

                        {{-- Payload that was sent --}}
                        <div x-show="responseTab === 'payload'" x-cloak class="space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <span class="text-ink-400">
                                    <span class="method mr-1" :class="`method-${result.request.method}`" x-text="result.request.method"></span>
                                    <span x-text="payloadIsQuery ? 'No request body: query parameters sent' : `Request body (${result.request.body_type === 'multipart' ? 'multipart/form-data' : 'application/json'})`"></span>
                                </span>
                                <button type="button" class="btn btn-secondary btn-sm" @click="copy(payloadText, 'Payload copied')">Copy payload</button>
                            </div>
                            <pre class="code-block max-h-[36rem]" x-html="payloadHtml"></pre>
                            <p class="text-xs text-ink-500">Exactly what was sent to Paystack. Sensitive values (PIN, OTP, card data) stay masked.</p>
                        </div>

                        {{-- Response headers --}}
                        <div x-show="responseTab === 'headers'" x-cloak class="overflow-x-auto">
                            <table class="table">
                                <tbody>
                                <template x-for="[name, value] in Object.entries(response.headers)" :key="name">
                                    <tr><td class="w-1/3 font-mono text-xs text-ink-400" x-text="name"></td><td class="break-all font-mono text-xs" x-text="value"></td></tr>
                                </template>
                                </tbody>
                            </table>
                        </div>

                        {{-- Request inspector --}}
                        <div x-show="responseTab === 'request'" x-cloak class="space-y-3 text-xs">
                            <div class="code-block break-all"><span class="method mr-1" :class="`method-${result.request.method}`" x-text="result.request.method"></span><span x-text="result.request.url"></span></div>
                            <table class="table">
                                <tbody>
                                <template x-for="[name, value] in Object.entries(result.request.headers)" :key="name">
                                    <tr><td class="w-1/3 font-mono text-ink-400" x-text="name"></td><td class="font-mono" x-text="value"></td></tr>
                                </template>
                                </tbody>
                            </table>
                            <template x-if="result.request.body">
                                <div>
                                    <div class="mb-1 font-semibold uppercase tracking-wide text-ink-400" x-text="`Body (${result.request.body_type})`"></div>
                                    <pre class="code-block" x-html="requestBodyHtml"></pre>
                                </div>
                            </template>
                            <p class="text-ink-500">The Authorization header is masked. Sensitive values (PIN, OTP, card data) are masked in this view and in history.</p>
                        </div>

                        {{-- cURL --}}
                        <div x-show="responseTab === 'curl'" x-cloak class="space-y-2">
                            <pre class="code-block whitespace-pre-wrap break-all" x-text="result.curl"></pre>
                            <button type="button" class="btn btn-secondary btn-sm" @click="copy(result.curl, 'cURL copied')">Copy cURL</button>
                            <p class="text-xs text-ink-500">Replace YOUR_SECRET_KEY with your own key. The explorer never includes real keys.</p>
                        </div>

                        {{-- Pagination --}}
                        <template x-if="pagination">
                            <div class="flex flex-wrap items-center gap-2 border-t border-ink-700 pt-3 text-xs">
                                <template x-if="pagination.type === 'page'">
                                    <div class="flex flex-wrap items-center gap-1">
                                        <button type="button" class="btn btn-secondary btn-sm" :disabled="pagination.page <= 1 || !hasPageParam || loading" @click="goToPage(pagination.page - 1)">Previous</button>
                                        <template x-for="n in pageWindow" :key="n">
                                            <button type="button" class="btn btn-sm" :class="n === pagination.page ? 'btn-primary' : 'btn-secondary'" :aria-current="n === pagination.page ? 'page' : null" :disabled="loading" @click="goToPage(n)" x-text="n"></button>
                                        </template>
                                        <button type="button" class="btn btn-secondary btn-sm" :disabled="pagination.page >= pagination.page_count || !hasPageParam || loading" @click="goToPage(pagination.page + 1)">Next</button>
                                        <span class="ml-2 text-ink-400" x-text="`Page ${pagination.page} of ${pagination.page_count}${pagination.total !== null ? ' · ' + pagination.total + ' total' : ''}`"></span>
                                    </div>
                                </template>
                                <template x-if="pagination.type === 'cursor'">
                                    <div class="flex items-center gap-1">
                                        <button type="button" class="btn btn-secondary btn-sm" :disabled="!pagination.previous || loading" @click="goToCursor(pagination.previous, 'previous')">Previous</button>
                                        <button type="button" class="btn btn-secondary btn-sm" :disabled="!pagination.next || loading" @click="goToCursor(pagination.next, 'next')">Next</button>
                                        <span class="ml-2 text-ink-400">Cursor pagination</span>
                                    </div>
                                </template>
                                <label x-show="perPageParam" class="ml-auto flex items-center gap-1 text-ink-400">Per page
                                    <select class="input w-20 py-1 text-xs" @change="setPerPage($event.target.value)" :disabled="loading">
                                        <template x-for="size in [10, 20, 50, 100]" :key="size">
                                            <option :value="size" :selected="Number(pagination.per_page) === size" x-text="size"></option>
                                        </template>
                                    </select>
                                </label>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </section>
    </div>

    {{-- Saved configurations & recent runs --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="card" aria-labelledby="saved-heading">
            <div class="card-header"><h2 id="saved-heading" class="font-semibold text-ink-100">Saved configurations</h2></div>
            <div class="card-body space-y-1 text-sm">
                <template x-if="saveMessage">
                    <a :href="saveMessage.url" class="nav-link text-emerald-300">New: <span x-text="saveMessage.name"></span></a>
                </template>
                @forelse ($savedRequests as $saved)
                    <div class="flex items-center justify-between gap-2">
                        <a href="{{ $endpoint->url(['saved' => $saved->id]) }}" class="nav-link flex-1 truncate">{{ $saved->name }}
                            <span class="ml-auto font-mono text-xs text-ink-500">{{ $saved->country_code }}/{{ $saved->currency_code }}</span></a>
                        <form method="POST" action="{{ route('explorer.saved.destroy', $saved) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost btn-sm" aria-label="Delete {{ $saved->name }}">✕</button>
                        </form>
                    </div>
                @empty
                    <p class="text-ink-400" x-show="!saveMessage">Use “Save…” to keep a named copy of the current parameters.</p>
                @endforelse
            </div>
        </section>
        <section class="card" aria-labelledby="runs-heading">
            <div class="card-header"><h2 id="runs-heading" class="font-semibold text-ink-100">Your recent runs</h2></div>
            <div class="card-body space-y-1 text-sm">
                @forelse ($recentRuns as $run)
                    <div class="flex items-center gap-2">
                        <x-status-badge :code="$run->status_code" :success="$run->success" />
                        <a href="{{ route('explorer.history.show', $run) }}" class="flex-1 truncate text-ink-300 hover:underline">{{ $run->created_at->diffForHumans() }} · {{ $run->country_code }}/{{ $run->currency_code }} · {{ $run->duration_ms }} ms</a>
                        <a href="{{ route('explorer.history.replay', $run) }}" class="btn btn-ghost btn-sm">Replay</a>
                    </div>
                @empty
                    <p class="text-ink-400">No runs yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Live confirmation --}}
    <div x-cloak x-show="liveConfirmOpen" class="fixed inset-0 z-[60] grid place-items-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="live-confirm-title" @keydown.escape.window="liveConfirmOpen = false">
        <div class="card w-full max-w-md border-red-500/60">
            <div class="card-body space-y-3">
                <h2 id="live-confirm-title" class="text-lg font-semibold text-red-300">Send a LIVE request?</h2>
                <p class="text-sm text-ink-300">
                    <span class="font-mono">{{ $endpoint->method }} {{ $endpoint->path }}</span> will be sent to Paystack with the
                    <strong>live</strong> credentials for {{ $context->country->name }}.
                </p>
                <template x-if="def.is_dangerous">
                    <div>
                        <p class="text-sm text-red-300">This endpoint can move real money or make an irreversible change.</p>
                        <label for="live-confirm-input" class="label mt-2">Type LIVE to confirm</label>
                        <input id="live-confirm-input" x-model="liveConfirmText" class="input font-mono" autocomplete="off">
                    </div>
                </template>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="liveConfirmOpen = false">Cancel</button>
                    <button type="button" class="btn btn-live" :disabled="!liveConfirmReady" @click="confirmLive()">Send live request</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div x-cloak x-show="toast" x-transition class="fixed bottom-4 right-4 z-[80] rounded-lg border border-ink-600 bg-ink-800 px-4 py-2 text-sm text-ink-100 shadow-xl" role="status" x-text="toast"></div>
</div>
@endsection

@push('islands')
<script type="application/json" id="endpoint-definition">@json($endpoint->toClientArray())</script>
<script type="application/json" id="endpoint-config">@json($formConfig)</script>
<script type="application/json" id="endpoint-prefill">@json(['params' => (object) $prefill, 'masked' => $maskedFields])</script>
@endpush
