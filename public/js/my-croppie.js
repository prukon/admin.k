// console.log(12);

// Обработка модалки для загрузки аватарки

document.addEventListener("DOMContentLoaded", (event) => {

    $(document).on('click', '#upload-aphoto', function () {
    document.getElementById('selectedFile').click();
});

    $('#selectedFile').change(function () {
    if (this.files[0] == undefined)
    return;
    $('#imageModalContainer').modal('show');
    let reader = new FileReader();
    reader.addEventListener("load", function () {
    window.src = reader.result;
    $('#selectedFile').val('');
}, false);
    if (this.files[0]) {
    reader.readAsDataURL(this.files[0]);
}
});

    let croppi;
    $('#imageModalContainer').on('shown.bs.modal', function () {
    let width = document.getElementById('crop-image-container').offsetWidth - 20;
    $('#crop-image-container').height((width - 80) + 'px');
    croppi = $('#crop-image-container').croppie({
    viewport: {
    width: width,
    height: width
},
});
    $('.modal-body1').height(document.getElementById('crop-image-container').offsetHeight + 50 + 'px');
    croppi.croppie('bind', {
    url: window.src,
}).then(function () {
    croppi.croppie('setZoom', 0);
});
});


    $('#imageModalContainer').on('hidden.bs.modal', function () {
        croppi.croppie('destroy');
    });

    $('#imageModalContainer').on('hidden.bs.modal', function () {
});


    $(document).on('click', '.cancel-modal', function (ev) {
        // croppi.croppie('destroy');
        $('.modal').modal('hide');

    });   $(document).on('click', '.close', function (ev) {
        // croppi.croppie('destroy');
        $('.modal').modal('hide');

    });

    $(document).on('click', '.save-modal', function (ev) {
    croppi.croppie('result', {
        type: 'base64',
        format: 'jpeg',
        size: 'original'
    }).then(function (resp) {
        // $('#confirm-img').attr('src', resp);
        // $('.modal').modal('hide');


        $.ajax({
            url:"upload.php",
            type: "POST",
            data:{"image": resp},
            success:function(data)
            {
                $('#confirm-img').attr('src', resp);
                $('.modal').modal('hide');
                console.log('ok');
            }
    });


    });
});

});
