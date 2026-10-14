<?php
namespace Database\Seeders;
use App\Models\{User,Club,Recruitment,Membership,Event,Competition,Assignment,ClubApplication};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB,Hash};
class DemoSeeder extends Seeder {
    public function run(): void {
        if (!app()->environment('local','testing') || !config('brudel.demo')) throw new \RuntimeException('DemoSeeder hanya untuk lingkungan local/testing dengan BRUDEL_DEMO=true.');
        DB::transaction(function () {
            DB::table('programs')->updateOrInsert(['id'=>1],['name'=>'D-III Keperawatan','department'=>'Keperawatan']);
            DB::table('programs')->updateOrInsert(['id'=>2],['name'=>'D-IV Gizi dan Dietetika','department'=>'Gizi']);
            $people=[['Bintang Mandagi','mahasiswa','DEMO001',1],['Michelle Sumual','anggota','DEMO002',2],['Andre Runtuwene','pengurus','DEMO003',1],['Pengelola Kemahasiswaan','admin',null,null],['Admin Pekan Prestasi','event',null,null]];
            foreach ($people as [$name,$email,$nim,$program]) $users[$email]=User::firstOrCreate(['email'=>$email.'@demo.brudel.test'],['name'=>$name,'nim'=>$nim,'program_id'=>$program,'password'=>Hash::make('BrudelDemo!2026'),'email_verified_at'=>now(),'active'=>true]);
            $clubs=[['Badminton','Olahraga','Temukan ritme permainanmu. Berlatih bersama, bangun sportivitas, dan bersiap menuju podium.','racket'],['Paduan Suara','Seni','Satukan suara, ciptakan harmoni. Ruang berkarya untuk kamu yang ingin tumbuh bersama musik.','music'],['Karya Tulis Ilmiah','Akademik','Dari rasa ingin tahu menjadi karya bermakna. Kembangkan ide, riset, dan kemampuan presentasimu.','book'],['Futsal','Olahraga','Bangun kerja sama dan ketangkasan di lapangan. Terbuka untuk pemain dari semua prodi.','ball'],['Seni Tari','Seni','Ekspresikan diri melalui tari tradisional dan kreasi. Rayakan keberagaman budaya bersama.','spark'],['English Club','Akademik','Asah debat, public speaking, dan bahasa Inggris bersama teman lintas prodi.','globe']];
            foreach ($clubs as $i=>[$name,$field,$desc,$symbol]) {
                $club=Club::firstOrCreate(['name'=>$name],['field'=>$field,'description'=>$desc,'mentor'=>'Pembina '.$name.' (demo)','contact'=>'Kontak resmi belum dikonfigurasi','symbol'=>$symbol]); $all[]=$club;
                Recruitment::firstOrCreate(['club_id'=>$club->id,'name'=>'Rekrutmen semester ganjil 2026 · Demo'],['opens_at'=>now()->subDays(7),'closes_at'=>now()->addDays(14+$i),'quota'=>30,'requirements'=>'Mahasiswa aktif Poltekkes Kemenkes Manado. Bersedia mengikuti latihan dan kegiatan UKM. Pendaftaran gratis.']);
            }
            foreach ([['pengurus','pengurus',$all[0]->id],['admin','kemahasiswaan',null]] as [$u,$role,$club]) Assignment::firstOrCreate(['user_id'=>$users[$u]->id,'role'=>$role,'club_id'=>$club],['starts_at'=>now()->subMonth(),'ends_at'=>now()->addYear()]);
            $event=Event::firstOrCreate(['name'=>'Pekan Prestasi Mahasiswa 2026 · Demo'],['organizer'=>'Kemahasiswaan Poltekkes Kemenkes Manado (demo)','status'=>'approved']);
            Assignment::firstOrCreate(['user_id'=>$users['event']->id,'role'=>'admin_event','event_id'=>$event->id],['starts_at'=>now()->subMonth(),'ends_at'=>now()->addYear()]);
            foreach ([['Badminton Tunggal','Olahraga','individual',1,1,'Lapangan kampus',false],['Lomba Poster Kesehatan','Seni','individual',1,1,'Aula kampus',true],['Karya Tulis Ilmiah','Akademik','team',2,3,'Ruang seminar',false],['Futsal Antarprodi','Olahraga','team',5,8,'Lapangan futsal',false]] as $i=>[$name,$field,$format,$min,$max,$location,$doc]) Competition::firstOrCreate(['event_id'=>$event->id,'name'=>$name],['field'=>$field,'format'=>$format,'description'=>'Jadikan potensi sebagai prestasi. Ikuti '.$name.' dan tunjukkan kemampuan terbaikmu bersama mahasiswa lintas prodi. Kegiatan ini adalah data demonstrasi.','requirements'=>'Mahasiswa aktif. '.($doc?'Unggah karya poster dalam PDF maksimal 5 MB.':'Lengkapi motivasi dan konfirmasi semua anggota jika berkelompok.'),'min_members'=>$min,'max_members'=>$max,'quota'=>24,'fee'=>0,'requires_document'=>$doc,'opens_at'=>now()->subDays(3),'closes_at'=>now()->addDays(5+$i*3),'starts_at'=>now()->addDays(20+$i),'location'=>$location]);
            $r=$all[2]->recruitments()->first();
            $a=ClubApplication::firstOrCreate(['recruitment_id'=>$r->id,'user_id'=>$users['mahasiswa']->id],['motivation'=>'Saya ingin mengembangkan riset kesehatan dan kemampuan menulis ilmiah.','status'=>'accepted']);
            Membership::firstOrCreate(['club_id'=>$all[2]->id,'user_id'=>$users['mahasiswa']->id],['club_application_id'=>$a->id,'joined_at'=>now()->subDays(2)]);
        });
    }
}
