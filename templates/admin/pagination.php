<nav aria-label="<?= escape($paginationLabel ?? 'Record pages') ?>" class="inline-actions list-pagination">
    <p>Page <?= $pagination['page'] ?> of <?= $pagination['pages'] ?></p>
    <?php if ($pagination['page'] > 1): ?><a class="small-button" href="?<?= escape(http_build_query(array_merge($paginationParams, ['page'=>$pagination['page']-1]))) ?>">Previous</a><?php endif; ?>
    <?php if ($pagination['page'] < $pagination['pages']): ?><a class="small-button" href="?<?= escape(http_build_query(array_merge($paginationParams, ['page'=>$pagination['page']+1]))) ?>">Next</a><?php endif; ?>
</nav>
