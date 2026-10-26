// Photos chosen in the panel. PHP drops a request over post_max_size without a word, so sizes are
// checked before sending. The strip under a product sends right after choosing; the new product form
// keeps the chosen photos, with previews, until the product is saved.
export default ({ maxFiles, maxFileBytes, maxTotalBytes }) => {
    // Kept out of Alpine's reactive state: DataTransfer takes real File objects, not proxies.
    let files = [];

    const megabytes = (bytes) => Math.floor(bytes / 1048576);

    return {
        previews: [],
        error: '',
        sending: false,

        problem(chosen) {
            const big = chosen.find((file) => file.size > maxFileBytes);
            const total = chosen.reduce((sum, file) => sum + file.size, 0);

            if (chosen.length > maxFiles) {
                return `Naraz dodasz do ${maxFiles} zdjęć`;
            }

            if (big) {
                return `${big.name} jest za duże — jedno zdjęcie do ${megabytes(maxFileBytes)} MB`;
            }

            // A little room for the rest of the form.
            return total + 65536 > maxTotalBytes ? `Naraz wyślę do ${megabytes(maxTotalBytes)} MB zdjęć — wybierz mniej` : '';
        },

        send(input) {
            this.error = this.problem([...input.files]);

            if (this.error) {
                input.value = '';
            } else if (input.files.length > 0) {
                this.sending = true;
                input.form.requestSubmit();
            }
        },

        // Choosing again adds to the photos already chosen, like in the prototype.
        add(input) {
            const chosen = [...files, ...input.files];

            this.error = this.problem(chosen);
            this.keep(input, this.error ? files : chosen);
        },

        remove(index) {
            this.error = '';
            this.keep(this.$refs.photos, files.filter((file, position) => position !== index));
        },

        keep(input, chosen) {
            const transfer = new DataTransfer();

            chosen.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
            files = chosen;

            this.previews.forEach((url) => URL.revokeObjectURL(url));
            this.previews = chosen.map((file) => URL.createObjectURL(file));
        },
    };
};
