@extends('layouts.admin2')

@section('content')
    <div class="col-md-12 main-content text-start">
        <h4 class="pt-3 pb-3">Настройки</h4>

        <!-- Вкладки -->
        <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $activeTab == 'setting' ? 'active' : '' }}"
                   href="{{ route('admin.setting.setting') }}"
                   id="setting-tab"
                   role="tab"
                   aria-controls="setting"
                   aria-selected="{{ $activeTab == 'setting' ? 'true' : 'false' }}">
                    Общие
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $activeTab == 'rule' ? 'active' : '' }}"
                   href="{{ route('admin.setting.rule') }}"
                   id="rule-tab"
                   role="tab"
                   aria-controls="rule"
                   aria-selected="{{ $activeTab == 'rule' ? 'true' : 'false' }}">
                    Права пользователей
                </a>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Контент вкладки "Права пользователей" -->
            <div class="tab-pane fade {{ $activeTab == 'rule' ? 'show active' : '' }}" id="profile" role="tabpanel">
                <div class="container-fluid">
                    <h4 class="pt-3 text-start">Права пользователей</h4>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                            <tr>
                                <th>Описание функционала</th>
                                @foreach($roles as $role)
                                    <th class="text-center">{{ $role->label ?? $role->name }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($permissions as $permission)
                                <tr>
                                    <td>
                                        {{ $permission->description ?? $permission->name }}
                                    </td>
                                    @foreach($roles as $role)
                                        <td class="text-center">
                                            @php
                                                // Проверяем, есть ли у роли данное право
                                                $hasPermission = $role->permissions->contains($permission->id);
                                            @endphp
                                            <input type="checkbox"
                                                   class="permission-checkbox"
                                                   data-role-id="{{ $role->id }}"
                                                   data-permission-id="{{ $permission->id }}"
                                                   @checked($hasPermission)
                                            />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS для AJAX-обновления -->
    {{--@push('scripts')--}}
        <script>
            {{--document.addEventListener('DOMContentLoaded', function () {--}}
                {{--console.log(1);--}}
                {{--const checkboxes = document.querySelectorAll('.permission-checkbox');--}}
                {{--checkboxes.forEach(function (checkbox) {--}}
                    {{--checkbox.addEventListener('change', function (e) {--}}
                        {{--const roleId = this.getAttribute('data-role-id');--}}
                        {{--const permissionId = this.getAttribute('data-permission-id');--}}
                        {{--const isChecked = this.checked;--}}

                        {{--fetch("{{ route('admin.setting.rule.toggle') }}", {--}}
                            {{--method: 'POST',--}}
                            {{--headers: {--}}
                                {{--'Content-Type': 'application/json',--}}
                                {{--'X-CSRF-TOKEN': '{{ csrf_token() }}'--}}
                            {{--},--}}
                            {{--body: JSON.stringify({--}}
                                {{--role_id: roleId,--}}
                                {{--permission_id: permissionId,--}}
                                {{--value: isChecked ? 'true' : 'false'--}}
                            {{--})--}}
                        {{--})--}}
                            {{--.then(response => response.json())--}}
                            {{--.then(data => {--}}
                                {{--if (data.success) {--}}
                                    {{--// Подсветим чекбокс зелёным--}}
                                    {{--this.style.backgroundColor = '#c3e6cb'; // Светло-зелёный--}}
                                    {{--setTimeout(() => {--}}
                                        {{--this.style.backgroundColor = '';--}}
                                    {{--}, 2000);--}}
                                {{--} else {--}}
                                    {{--alert('Ошибка при обновлении!');--}}
                                {{--}--}}
                            {{--})--}}
                            {{--.catch(error => {--}}
                                {{--alert('Ошибка при соединении!');--}}
                                {{--console.error(error);--}}
                            {{--});--}}
                    {{--});--}}
                {{--});--}}
            {{--});--}}
        </script>
    <script>
        $(document).ready(function() {
            $('.permission-checkbox').on('change', function() {
                let roleId = $(this).data('role-id');
                let permissionId = $(this).data('permission-id');
                let isChecked = $(this).is(':checked');

                $.ajax({
                    url: "{{ route('admin.setting.rule.toggle') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        role_id: roleId,
                        permission_id: permissionId,
                        value: isChecked ? 'true' : 'false'
                    },
                    success: (response) => {
                        if (response.success) {

                            $(this).closest('td').addClass('table-success');
                            setTimeout(() => {
                                $(this).closest('td').removeClass('table-success');
                            }, 2000);

                        } else {
                            alert('Ошибка при обновлении прав!');
                        }
                    },
                    error: () => {
                        alert('Ошибка при соединении!');
                    }
                });
            });
        });
    </script>
    {{--@endpush--}}
@endsection
