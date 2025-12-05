<template>
    <div class="flex h-full w-full flex-col items-center justify-center gap-4 text-white">
        <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-white/30 shadow-lg">
            <video
                ref="video"
                class="h-64 w-full bg-black object-cover"
                playsinline
                autoplay
                muted
            ></video>
            <canvas ref="canvas" class="hidden"></canvas>
        </div>

        <p v-if="error" class="text-sm text-red-200">
            {{ error }}
        </p>
        <p v-else class="text-xs text-white/80">
            Placez le QR code dans le cadre.
        </p>

        <button
            type="button"
            class="rounded-full border border-white/40 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10"
            @click="stopScanner"
        >
            Fermer
        </button>
    </div>
</template>

<script>
export default {
    name: 'QrScanner',
    emits: ['close', 'detected'],
    data() {
        return {
            detector: null,
            animationFrame: null,
            error: '',
            stream: null,
        };
    },
    mounted() {
        this.initScanner();
    },
    beforeUnmount() {
        this.cleanup();
    },
    methods: {
        async initScanner() {
            if (!('BarcodeDetector' in window)) {
                this.error = 'Le scanner n’est pas supporté sur ce navigateur.';

                return;
            }

            try {
                this.detector = new window.BarcodeDetector({
                    formats: ['qr_code'],
                });
            } catch (error) {
                this.error = 'Impossible de démarrer le scanner.';

                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.scanFrame();
            } catch (error) {
                this.error = 'Accès à la caméra refusé.';
            }
        },
        async scanFrame() {
            if (!this.detector || !this.$refs.video) {
                return;
            }

            const video = this.$refs.video;

            if (video.readyState < 2) {
                this.animationFrame = requestAnimationFrame(() => this.scanFrame());

                return;
            }

            const canvas = this.$refs.canvas;
            const ctx = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            try {
                const barcodes = await this.detector.detect(canvas);
                if (barcodes.length) {
                    const value = barcodes[0].rawValue || '';
                    this.$emit('detected', value);
                    this.stopScanner(false);

                    return;
                }
            } catch (error) {
                // Ignore detection errors and continue scanning.
            }

            this.animationFrame = requestAnimationFrame(() => this.scanFrame());
        },
        stopScanner(triggerClose = true) {
            this.cleanup();
            if (triggerClose) {
                this.$emit('close');
            }
        },
        cleanup() {
            if (this.animationFrame) {
                cancelAnimationFrame(this.animationFrame);
                this.animationFrame = null;
            }
            if (this.detector) {
                this.detector = null;
            }
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
            if (this.$refs.video) {
                this.$refs.video.srcObject = null;
            }
        },
    },
};
</script>
