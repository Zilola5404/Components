<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
global $APPLICATION;
?>
<div id="residents-list-ajax">
    <?php if (!empty($arResult['ITEMS'])): ?>
        <ul>
            <?php foreach ($arResult['ITEMS'] as $item): ?>
                <li>
                    <?= htmlspecialchars($item['FIO']) ?> -
                    <?php if (!empty($item['HOME'])): ?>
                        <?= htmlspecialchars($item['HOME']['CITY']) ?>,
                        <?= htmlspecialchars($item['HOME']['STREET']) ?>,
                        <?= htmlspecialchars($item['HOME']['NUMBER']) ?>
                    <?php else: ?>
                        Дом не указан
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Нет данных</p>
    <?php endif; ?>

    <?php
    $APPLICATION->IncludeComponent(
        "bitrix:main.pagenavigation",
        "",
        [
            "NAV_OBJECT" => $arResult['NAV'],
            "SEF_MODE" => "N",
            "SHOW_ALWAYS" => "Y"
        ],
        false
    );
    ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function bindAjaxLinks() {
        document.querySelectorAll('#residents-list-ajax .main-ui-pagination a').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                let url = this.href;
                fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(response => response.text())
                    .then(html => {
                        let parser = new DOMParser();
                        let doc = parser.parseFromString(html, 'text/html');
                        let newList = doc.querySelector('#residents-list-ajax');
                        if (newList) {
                            document.getElementById('residents-list-ajax').innerHTML = newList.innerHTML;
                            bindAjaxLinks();
                        }
                    });
            });
        });
    }
    bindAjaxLinks();
});
</script> 
