<!-- 사이트 설정 이미지 필드(로고/파비콘 등)용 미디어 선택 모달 -->
<div class="modal fade" id="mediaPickerModal" tabindex="-1"
     aria-labelledby="mediaPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0" id="mediaPickerModalLabel">미디어 선택</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <?php /* 목록이 통째로 갈리므로 라이브 리전으로 두어 변화를 알린다 */ ?>
                <div class="row g-2" id="mediaPickerGrid" aria-live="polite" aria-busy="false"></div>
            </div>
            <div class="modal-footer justify-content-center">
                <nav aria-label="미디어 페이지">
                    <ul class="pagination pagination-sm mb-0" id="mediaPickerPagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>
