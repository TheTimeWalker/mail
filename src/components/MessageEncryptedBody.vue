<template>
	<div>
		<div v-if="mailvelopeAvailable" id="mail-content"></div>
		<span v-else>{{ t('mail', 'This message is encrypted with PGP. Install Mailvelope to decrypt it.') }}</span>
	</div>
</template>

<script>
import logger from '../logger'

export default {
	name: 'MessageEncryptedBody',
	props: {
		body: {
			type: String,
			required: true,
		},
		from: {
			type: String,
			required: false,
			default: undefined,
		},
	},
	data() {
		return {
			mailvelopeAvailable: false,
		}
	},
	beforeMount() {
		if (window.mailvelope) {
			this.mailvelopeAvailable = true
		} else {
			window.addEventListener('mailvelope', this.onMailvelopeLoaded, false)
		}
	},
	mounted() {
		window.mailvelope.createDisplayContainer('#mail-content', this.body, undefined, {
			senderAddress: this.from,
		})
	},
	methods: {
		onMailvelopeLoaded() {
			logger.debug('mailvelope loaded')
			this.mailvelopeAvailable = true
		},
	},
}
</script>

<style scoped>
#mail-content {
	height: 450px;
}
</style>
