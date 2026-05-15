@extends('layouts.app')

@section('title', 'Search')

@section('content')
<div x-data="search">

    {{-- Search form --}}
    <form @submit.prevent="search(1)" class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <label for="postcode" class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                <input
                    id="postcode"
                    type="text"
                    x-model="postcode"
                    placeholder="e.g. SW1A 1AA"
                    maxlength="8"
                    class="w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :class="validationErrors.postcode ? 'border-red-400' : 'border-gray-300'"
                >
                <p x-show="validationErrors.postcode" x-text="validationErrors.postcode?.[0]" class="mt-1 text-xs text-red-600"></p>
            </div>

            <div>
                <label for="radius" class="block text-sm font-medium text-gray-700 mb-1">Radius</label>
                <select id="radius" x-model="radius" class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="0.5">0.5 miles</option>
                    <option value="1" selected>1 mile</option>
                    <option value="2">2 miles</option>
                </select>
            </div>

            <div class="flex items-end">
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full sm:w-auto rounded-md bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                >
                    <span x-show="!loading">Search</span>
                    <span x-show="loading">Searching…</span>
                </button>
            </div>
        </div>

        {{-- Optional filters --}}
        <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Property type</p>
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach(['D' => 'Detached', 'S' => 'Semi', 'T' => 'Terraced', 'F' => 'Flat', 'O' => 'Other'] as $code => $label)
                    <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" value="{{ $code }}" x-model="propertyTypes" class="rounded border-gray-300 text-blue-600">
                        {{ $label }}
                    </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Sold from</label>
                <input id="date_from" type="date" x-model="dateFrom" class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Sold to</label>
                <input id="date_to" type="date" x-model="dateTo" class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
    </form>

    {{-- General error --}}
    <div x-show="error" class="mt-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700" x-text="error"></div>

    {{-- Area summary panel --}}
    <div x-show="searched && !summaryError" class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Crime --}}
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Crime in this area <span class="font-normal text-gray-400">(last 12 months)</span></h2>

            {{-- Loading skeleton --}}
            <div x-show="summaryLoading" class="space-y-2 animate-pulse">
                <template x-for="i in 5" :key="i">
                    <div class="flex justify-between">
                        <div class="h-3 bg-gray-200 rounded w-32"></div>
                        <div class="h-3 bg-gray-200 rounded w-8"></div>
                    </div>
                </template>
            </div>

            {{-- Crime table --}}
            <div x-show="!summaryLoading && areaSummary">
                <template x-if="areaSummary?.crime?.length === 0">
                    <p class="text-sm text-gray-400">No recorded crimes in this area.</p>
                </template>
                <template x-if="areaSummary?.crime?.length > 0">
                    <div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-400 border-b border-gray-100">
                                    <th class="text-left font-normal pb-1.5">Category</th>
                                    <th class="text-right font-normal pb-1.5 pr-3">This area</th>
                                    <th class="text-right font-normal pb-1.5" x-show="crimeComparison" x-text="crimeComparison?.neighbourhood ?? 'Neighbourhood'"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-for="row in mergedCrimeRows()" :key="row.category">
                                    <tr>
                                        <td class="py-1 text-gray-600" x-text="formatCrimeCategory(row.category)"></td>
                                        <td class="py-1 pr-3 text-right font-medium text-gray-900 tabular-nums" x-text="row.area"></td>
                                        <td class="py-1 text-right tabular-nums" x-show="crimeComparison">
                                            <span class="text-gray-500" x-text="row.neighbourhood"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        {{-- Comparison loading / error states --}}
                        <div x-show="comparisonLoading" class="mt-3 flex items-center gap-1.5 text-xs text-gray-400">
                            <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                            Fetching neighbourhood comparison…
                        </div>
                        <div x-show="crimeComparison && !comparisonLoading" class="mt-2 text-xs text-gray-400" x-text="crimeComparison?.force"></div>
                        <div x-show="comparisonError && !comparisonLoading" class="mt-2 text-xs text-gray-400">Neighbourhood comparison unavailable.</div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Schools --}}
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Schools within 1 mile</h2>
            <div x-show="summaryLoading" class="space-y-2 animate-pulse">
                <template x-for="i in 4" :key="i">
                    <div class="flex justify-between">
                        <div class="h-3 bg-gray-200 rounded w-40"></div>
                        <div class="h-3 bg-gray-200 rounded w-12"></div>
                    </div>
                </template>
            </div>
            <div x-show="!summaryLoading && areaSummary">
                <template x-if="areaSummary?.schools?.length === 0">
                    <p class="text-sm text-gray-400">No schools found within 1 mile.</p>
                </template>
                <ul class="space-y-2">
                    <template x-for="school in areaSummary?.schools ?? []" :key="school.urn">
                        <li class="flex items-start justify-between gap-2 text-sm">
                            <div class="min-w-0">
                                <span class="text-gray-900 font-medium" x-text="school.name"></span>
                                <template x-if="school.phase">
                                    <span class="ml-1.5 inline-flex items-center rounded-full bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-700 cursor-help" x-text="school.phase" :title="phaseDescriptions[school.phase] ?? school.phase"></span>
                                </template>
                                <template x-if="!school.phase && school.type">
                                    <span class="ml-1.5 inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 cursor-help" x-text="school.type" title="This school does not follow a standard phase structure"></span>
                                </template>
                            </div>
                            <span class="text-gray-400 shrink-0 tabular-nums" x-text="formatDistance(school.distance_metres)"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>

    </div>

    {{-- Loading skeleton --}}
    <div x-show="loading" class="mt-6 space-y-3">
        <template x-for="i in 5" :key="i">
            <div class="bg-white border border-gray-200 rounded-lg p-4 animate-pulse">
                <div class="flex justify-between">
                    <div class="h-4 bg-gray-200 rounded w-48"></div>
                    <div class="h-5 bg-gray-200 rounded w-24"></div>
                </div>
                <div class="mt-2 h-3 bg-gray-100 rounded w-32"></div>
            </div>
        </template>
    </div>

    {{-- Results --}}
    <div x-show="!loading && searched">
        <div class="mt-6 flex items-center justify-between">
            <p class="text-sm text-gray-600">
                <span x-show="pagination">
                    <span x-text="pagination?.total ?? 0"></span> results near
                    <strong x-text="postcode.toUpperCase()"></strong>
                </span>
                <span x-show="!pagination && searched">No results found.</span>
            </p>
        </div>

        <div class="mt-3 space-y-3">
            <template x-for="sale in results" :key="sale.id">
                <div class="bg-white border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 truncate" x-text="sale.address"></p>
                            <p class="text-sm text-gray-500 mt-0.5">
                                <span x-text="sale.town"></span>
                                <span class="mx-1">·</span>
                                <span x-text="sale.postcode"></span>
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-lg font-semibold text-gray-900" x-text="formatPrice(sale.sold_price)"></p>
                            <p class="text-xs text-gray-500 mt-0.5" x-text="formatDate(sale.sale_date)"></p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                        {{-- Property type badge --}}
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-700" x-text="typeLabels[sale.property_type] ?? sale.property_type"></span>

                        {{-- New build --}}
                        <span x-show="sale.new_build" class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-700">New build</span>

                        {{-- Floor area --}}
                        <span x-show="sale.floor_area_sqm">
                            <span x-text="sale.floor_area_sqm"></span> m²
                        </span>

                        {{-- Price per sqm --}}
                        <span x-show="sale.price_per_sqm">
                            · <span x-text="formatPrice(sale.price_per_sqm)"></span>/m²
                        </span>

                        {{-- Distance --}}
                        <span x-show="sale.distance_metres != null" class="ml-auto">
                            <span x-text="formatDistance(sale.distance_metres)"></span> away
                        </span>

                        {{-- EPC badge --}}
                        <template x-if="sale.epc_match_confidence === 'exact'">
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 font-medium text-green-700">EPC matched</span>
                        </template>
                        <template x-if="sale.epc_match_confidence === 'fuzzy'">
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-700" title="Address matched approximately — size data may not be exact">EPC ~matched</span>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Pagination --}}
        <div x-show="pagination && pagination.last_page > 1" class="mt-6 flex items-center justify-between">
            <p class="text-sm text-gray-600">
                Page <span x-text="pagination?.current_page"></span> of <span x-text="pagination?.last_page"></span>
            </p>
            <div class="flex gap-2">
                <button
                    @click="search(pagination.current_page - 1)"
                    :disabled="pagination?.current_page <= 1"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Previous
                </button>
                <button
                    @click="search(pagination.current_page + 1)"
                    :disabled="pagination?.current_page >= pagination?.last_page"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Next
                </button>
            </div>
        </div>
    </div>

    {{-- Empty state --}}
    <div x-show="!loading && !searched && !error" class="mt-16 text-center text-gray-400">
        <p class="text-4xl mb-3">🏠</p>
        <p class="text-sm">Enter a postcode to see nearby property sales.</p>
    </div>

</div>
@endsection
