'use strict';
var notify = $.notify('<i class="fa fa-bell-o"></i><strong>Cargando</strong> Aplicación no cierre esta página...', {
    type: 'theme',
    allow_dismiss: true,
    delay: 2000,
    showProgressbar: true,
    timer: 300
});

setTimeout(function () {
    notify.update('message', '<i class="fa fa-bell-o"></i><strong>Cargando</strong> Cargando Datos.');
}, 1000);
