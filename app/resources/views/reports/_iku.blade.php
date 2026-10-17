<section class="panel table-panel">
    <div class="section-heading padded"><div><h2>Prestasi mahasiswa sesuai IKU per jurusan{{ $year ? ' · '.$year : '' }}</h2><p>Penghargaan terverifikasi per tingkat. Penghargaan tim lintas jurusan muncul di setiap jurusan terkait, tetapi dihitung sekali pada total kampus.</p></div></div>
    <div class="table-wrap"><table class="compact iku-table"><thead><tr><th>Jurusan</th>@foreach(config('brudel.levels') as $label)<th>{{ $label }}</th>@endforeach<th>Total</th><th>Mahasiswa unik</th></tr></thead><tbody>
    @foreach($iku['rows'] as $row)
        <tr><td><strong>{{ $row['department']->name }}</strong></td>@foreach($row['levels'] as $count)<td class="{{ $count ? '' : 'muted' }}">{{ $count }}</td>@endforeach<td><strong>{{ $row['total'] }}</strong></td><td>{{ $row['students'] }}</td></tr>
    @endforeach
    </tbody><tfoot><tr><th>{{ ($iku['scoped'] ?? false) ? 'Total' : 'Total kampus' }}</th>@foreach($iku['totals']['levels'] as $count)<th>{{ $count }}</th>@endforeach<th>{{ $iku['totals']['total'] }}</th><th>{{ $iku['totals']['students'] }}</th></tr></tfoot></table></div>
</section>
