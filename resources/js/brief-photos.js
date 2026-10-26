/*
 * Photos in the custom order form. A phone photo has 24–48 Mpx and 5–20 MB, so each one is shrunk in the
 * browser to 2400 px on the longer side before sending — the same size the server keeps. A photo the browser
 * can't draw goes up as it is. PHP drops a request over post_max_size without a word, so sizes are checked too.
 */
const LONGEST_SIDE = 2400;

const shrink = async (file) => {
    try {
        // Turned upright from EXIF by the browser, like the photo looks in the gallery.
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, LONGEST_SIDE / Math.max(bitmap.width, bitmap.height));

        if (scale === 1 && file.size < 1048576) {
            bitmap.close();

            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86));

        return blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
    } catch {
        return file;
    }
};

export default ({ maxFiles, maxFileBytes, maxTotalBytes }) => {
    // Kept out of Alpine's reactive state: DataTransfer takes real File objects, not proxies.
    let files = [];

    const megabytes = (bytes) => Math.floor(bytes / 1048576);

    return {
        previews: [],
        error: '',
        preparing: false,
        sending: false,
        sendWhenReady: false,

        problem(chosen) {
            const big = chosen.find((file) => file.size > maxFileBytes);
            const total = chosen.reduce((sum, file) => sum + file.size, 0);

            if (chosen.length > maxFiles) {
                return `Wyślę do ${maxFiles} zdjęć — wybierz te, na których najlepiej widać pomysł`;
            }

            if (big) {
                return `${big.name} jest za duże — jedno zdjęcie do ${megabytes(maxFileBytes)} MB`;
            }

            // A little room for the rest of the form.
            return total + 65536 > maxTotalBytes ? `Naraz wyślę do ${megabytes(maxTotalBytes)} MB zdjęć — wybierz mniej` : '';
        },

        // Choosing again adds to the photos already chosen.
        async add(input) {
            const picked = [...input.files];

            this.preparing = true;
            const shrunk = await Promise.all(picked.map(shrink));
            const chosen = [...files, ...shrunk];
            this.preparing = false;

            this.error = this.problem(chosen);
            this.keep(input, this.error ? files : chosen);

            if (this.sendWhenReady) {
                this.sendWhenReady = false;
                input.form.requestSubmit();
            }
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

        submit(event) {
            // Photos still shrinking go up once they are ready; a second tap doesn't send the form twice.
            if (this.preparing) {
                event.preventDefault();
                this.sendWhenReady = true;
            } else if (this.sending) {
                event.preventDefault();
            } else {
                this.sending = true;
            }
        },
    };
};
