<?php

    $name = "site.com";
    $port1 = 6000;
    $port2 = 6001;

    // Adicionar Site ao VESTA
    $ssh->exec("export VESTA=/var/lib/docker/volumes/d9091675017a1fdd7f2898d30ee03604e3e1fb225c4d1ea2cc712fc9ef7102ab/_data/local/vesta/
                /usr/bin/sudo /var/lib/docker/volumes/d9091675017a1fdd7f2898d30ee03604e3e1fb225c4d1ea2cc712fc9ef7102ab/_data/local/vesta/bin/v-add-domain admin $name");
    // Ativar SSL ao site
    $ssh->exec("export VESTA=/var/lib/docker/volumes/d9091675017a1fdd7f2898d30ee03604e3e1fb225c4d1ea2cc712fc9ef7102ab/_data/local/vesta/
                /usr/bin/sudo /var/lib/docker/volumes/d9091675017a1fdd7f2898d30ee03604e3e1fb225c4d1ea2cc712fc9ef7102ab/_data/local/vesta/bin/v-add-letsencrypt-domain admin $name");
    // Substituir Portas 3000 e 3001 pelas reais
    $ssh->exec("find /var/lib/docker/volumes/ac7ab06a3d60ab56728b723b9c20d9d25f16de8a62257070f0f27caf9ac7ba98/_data/admin/conf/web/".$name.".nginx.conf -type f | xargs -d '\n' perl -pi -e 's/".$port1."/3000/g'");
    $ssh->exec("find /var/lib/docker/volumes/ac7ab06a3d60ab56728b723b9c20d9d25f16de8a62257070f0f27caf9ac7ba98/_data/admin/conf/web/".$name.".nginx.conf -type f | xargs -d '\n' perl -pi -e 's/".$port2."/3001/g'");
    $ssh->exec("find /var/lib/docker/volumes/ac7ab06a3d60ab56728b723b9c20d9d25f16de8a62257070f0f27caf9ac7ba98/_data/admin/conf/web/".$name.".nginx.ssl.conf -type f | xargs -d '\n' perl -pi -e 's/".$port1."/3000/g'");
    $ssh->exec("find /var/lib/docker/volumes/ac7ab06a3d60ab56728b723b9c20d9d25f16de8a62257070f0f27caf9ac7ba98/_data/admin/conf/web/".$name.".nginx.ssl.conf -type f | xargs -d '\n' perl -pi -e 's/".$port2."/3001/g'");


?>