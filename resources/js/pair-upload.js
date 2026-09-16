/*
 * The „przed i po” form in the panel. PHP drops a request over post_max_size without a word, so the sizes
 * of both photos are checked before sending, and each chosen photo shows a preview.
 */
export default ({ maxFileBytes, maxTotalBytes }) => {
    const megabytes = (bytes) => Math.floor(bytes / 1048576);

    return {
        error: '',
        previews: { before: '', after: '' },
        sending: false,

        choose(side, input) {
            const file = input.files[0];

            if (this.previews[side]) {
                URL.revokeObjectURL(this.previews[side]);
            }

            this.previews[side] = file ? URL.createObjectURL(file) : '';
            this.error = '';
        },

        submit(event) {
            const files = [...event.target.querySelectorAll('input[type=file]')].flatMap((input) => [...input.files]);
            const big = files.find((file) => file.size > maxFileBytes);
            const total = files.reduce((sum, file) => sum + file.size, 0);

            this.error = big
                ? `${big.name} jest za duże — jedno zdjęcie do ${megabytes(maxFileBytes)} MB`
                : total + 65536 > maxTotalBytes ? `Oba zdjęcia razem mogą mieć do ${megabytes(maxTotalBytes)} MB — wybierz mniejsze` : '';

            if (this.error) {
                event.preventDefault();
            } else {
                this.sending = true;
            }
        },
    };
};
