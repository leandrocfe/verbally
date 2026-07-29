let container = this.$el.querySelector('[data-conversation-scroll]');

if (container) {
    let nearBottom = true;

    container.addEventListener('scroll', () => {
        nearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 120;
    });

    let observer = new MutationObserver(() => {
        if (nearBottom) {
            container.scrollTop = container.scrollHeight;
        }
    });

    observer.observe(container, { childList: true, subtree: true, characterData: true });
}
