{{--
    Banner de campaña full-bleed compartido por carrito, checkout y selección (product).
    Todas las vistas usan esta misma regla para que el banner mida lo mismo en cada pantalla:
    - Altura fija por proporción (aspect-ratio 1103/318, la del banner de diseño).
    - object-fit: contain -> la imagen completa se ve siempre, sin recortes.
    - Sin límite de altura: la imagen conserva su proporción en todas las pantallas.
    Requiere la variable $campaignBannerUrl (la vista debe comprobar que no esté vacía).
--}}
<style>
    .campaign-banner-bleed {
        width: 100vw;
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);
        position: relative;
        isolation: isolate;
        background-color: #fff;
    }

    .campaign-banner-bleed img {
        display: block;
        width: 100%;
        height: auto;
        aspect-ratio: 1103 / 318;
        object-fit: contain;
        object-position: center;
    }
</style>
<div class="campaign-banner-bleed">
    <img src="{{ $campaignBannerUrl }}" alt="Banner campaña" loading="lazy">
</div>
