<x-app-layout>
    <x-slot name="header">จัดการผู้ใช้งาน</x-slot>

    <div class="page-head">
        <h1 class="h1">จัดการผู้ใช้งาน</h1>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="h2">รายชื่อผู้ใช้งาน</div>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อ</th>
                            <th>อีเมล</th>
                            <th>สิทธิ์การใช้งาน</th>
                            <th>แผนก</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="patient-name">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td><span class="chip chip-casetype">{{ $user->roleLabel() }}</span></td>
                                <td>{{ $user->department ?: '—' }}</td>
                                <td style="text-align:right;">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary btn-sm">แก้ไขสิทธิ์</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
