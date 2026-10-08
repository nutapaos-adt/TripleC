<x-app-layout>
    <x-slot name="header">จัดการผู้ใช้งาน</x-slot>

    <div class="page-head">
        <h1 class="h1">ผู้ใช้งาน</h1>
        <p class="sub">จัดการบทบาทและหน่วยงานของผู้ใช้งานในระบบ</p>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อ</th>
                            <th>อีเมล</th>
                            <th>บทบาท</th>
                            <th>หน่วยงาน</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="patient-name">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td><span class="chip {{ $user->roleChipClass() }}">{{ $user->roleLabel() }}</span></td>
                                <td>{{ $user->ward?->name ?? '—' }}</td>
                                <td style="text-align:right;">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary btn-sm">แก้ไข</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
