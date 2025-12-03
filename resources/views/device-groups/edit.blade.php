@extends('layouts.app')

@section('title', 'Edit ' . $deviceGroup->name)

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <a href="{{ route('device-groups.show', $deviceGroup) }}" class="text-blue-600 dark:text-blue-400 hover:underline">&larr; Back to Group</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Edit Device Group</h1>

        <form action="{{ route('device-groups.update', $deviceGroup) }}" method="POST" x-data="ruleBuilder()">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                {{-- Basic Info --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Group Name *</label>
                        <input type="text" name="name" id="name" required
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            value="{{ old('name', $deviceGroup->name) }}">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                        <input type="number" name="priority" id="priority" min="0"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            value="{{ old('priority', $deviceGroup->priority) }}">
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea name="description" id="description" rows="2"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $deviceGroup->description) }}</textarea>
                </div>

                {{-- Match Type --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Match Type *</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="match_type" value="all" x-model="matchType"
                                class="text-blue-600 focus:ring-blue-500 dark:bg-gray-700">
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Match ALL rules (AND)</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="match_type" value="any" x-model="matchType"
                                class="text-blue-600 focus:ring-blue-500 dark:bg-gray-700">
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Match ANY rule (OR)</span>
                        </label>
                    </div>
                </div>

                {{-- Rules --}}
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Membership Rules *</label>
                        <button type="button" @click="addRule()"
                            class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                            + Add Rule
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(rule, index) in rules" :key="index">
                            <div class="flex gap-2 items-start bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                <div class="flex-1">
                                    <select :name="'rules[' + index + '][field]'" x-model="rule.field" @change="rule.value = ''" required
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white text-sm">
                                        <option value="">Select field...</option>
                                        @foreach($fields as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <select :name="'rules[' + index + '][operator]'" x-model="rule.operator" required
                                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white text-sm">
                                        <option value="">Select operator...</option>
                                        @foreach($operators as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-1" x-show="!['is_null', 'is_not_null'].includes(rule.operator)">
                                    {{-- Dropdown for fields with known values --}}
                                    <template x-if="fieldValues[rule.field]">
                                        <select :name="'rules[' + index + '][value]'" x-model="rule.value"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white text-sm">
                                            <option value="">Select value...</option>
                                            <template x-for="val in fieldValues[rule.field]" :key="val">
                                                <option :value="val" :selected="val === rule.value" x-text="val"></option>
                                            </template>
                                        </select>
                                    </template>
                                    {{-- Text input for fields without predefined values --}}
                                    <template x-if="!fieldValues[rule.field]">
                                        <input type="text" :name="'rules[' + index + '][value]'" x-model="rule.value"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-white text-sm"
                                            :placeholder="getPlaceholder(rule.field)">
                                    </template>
                                </div>
                                <button type="button" @click="removeRule(index)" x-show="rules.length > 1"
                                    class="text-red-500 hover:text-red-700 p-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Preview --}}
                <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-blue-800 dark:text-blue-200">
                            Matching Devices: <span x-text="previewCount" class="font-bold"></span>
                        </span>
                        <button type="button" @click="previewRules()"
                            class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                            Refresh Preview
                        </button>
                    </div>
                </div>

                {{-- Active Status --}}
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ $deviceGroup->is_active ? 'checked' : '' }}
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-gray-700">
                        <span class="ml-2 text-gray-700 dark:text-gray-300">Active</span>
                    </label>
                </div>

                {{-- Submit --}}
                <div class="flex justify-end gap-3">
                    <a href="{{ route('device-groups.show', $deviceGroup) }}"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function ruleBuilder() {
    // Store the initial rules from PHP with their values
    const initialRules = @json($deviceGroup->rules->map(fn($r) => ['field' => $r->field, 'operator' => $r->operator, 'value' => $r->value])->values());

    return {
        matchType: '{{ $deviceGroup->match_type }}',
        rules: initialRules,
        previewCount: {{ $deviceGroup->getMatchingDeviceCount() }},
        fieldValues: @json($fieldValues),

        init() {
            // Force Alpine to re-evaluate the rules after the component is fully mounted
            // This ensures dropdown values are properly selected
            this.$nextTick(() => {
                // Create a shallow copy and reassign to trigger reactivity
                this.rules = [...this.rules];
            });
        },

        addRule() {
            this.rules.push({ field: '', operator: '', value: '' });
        },

        removeRule(index) {
            this.rules.splice(index, 1);
        },

        getPlaceholder(field) {
            const placeholders = {
                'serial_number': 'e.g., CXNK001D09DF',
                'ip_address': 'e.g., 192.168.1. or 10.0.0.1',
                'subscriber_id': 'Subscriber ID number',
                'tags': 'Tag name',
                'last_inform': 'YYYY-MM-DD HH:MM:SS',
                'created_at': 'YYYY-MM-DD HH:MM:SS',
            };
            return placeholders[field] || 'Value';
        },

        async previewRules() {
            try {
                const response = await fetch('{{ route("device-groups.preview-rules") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        match_type: this.matchType,
                        rules: this.rules.filter(r => r.field && r.operator)
                    })
                });
                const data = await response.json();
                this.previewCount = data.count;
            } catch (e) {
                console.error('Preview failed:', e);
            }
        }
    }
}
</script>
@endsection
