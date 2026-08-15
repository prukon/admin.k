<script>
    window.__trainerTypesConfig = {
        listUrl: @json(route('admin.trainer-types.index')),
        storeUrl: @json(route('admin.trainer-types.store')),
        updateUrlTemplate: @json(route('admin.trainer-types.update', ['trainerType' => '__ID__'])),
        destroyUrlTemplate: @json(route('admin.trainer-types.destroy', ['trainerType' => '__ID__'])),
        canManage: @json((bool) ($canManageTrainerTypes ?? false)),
    };
</script>
<script src="{{ asset('js/trainer-types.js') }}?v={{ @filemtime(public_path('js/trainer-types.js')) ?: time() }}"></script>
