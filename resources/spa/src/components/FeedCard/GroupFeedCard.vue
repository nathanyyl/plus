<script>
import FeedCard from './FeedCard.vue'
import * as api from '@/api/group.js'

export default {
  name: 'GroupFeedCard',
  extends: FeedCard,
  props: {
    group: { type: Object, default: () => {} },
  },
  computed: {
    post () {
      return this.feed || {}
    },
    currentUser () {
      return this.$store.state.CURRENTUSER
    },
    liked: {
      get () {
        return !!this.feed.liked
      },
      set (val) {
        this.feed.liked = val
      },
    },
    commentCount: {
      get () {
        return this.feed.comments_count || 0
      },
      set (val) {
        this.feed.comments_count = ~~val
      },
    },
    likeCount: {
      get () {
        return this.feed.likes_count || 0
      },
      set (val) {
        this.feed.likes_count = ~~val
      },
    },
    body () {
      const body = this.feed.summary || ''
      return body.replace(/@!\[image\]\(\d+\)/g, '')
    },
    images () {
      let images = []
      this.feed.images.map(image => {
        image.file = image.id
        images = [...images, image]
      })

      return images || []
    },
    has_collect: {
      get () {
        return this.feed.collected || 0
      },
      set (val) {
        this.feed.collected = val
      },
    },
    viewCount () {
      return this.feed.views_count || 0
    },
    isGroupOwner () {
      return this.group.founder.user_id === this.currentUser.id
    },
    isGroupManager () {
      const { role = '' } = this.group.joined || {}
      return ['founder', 'administrator'].includes(role)
    },
  },
  methods: {
    handleView () {
      this.$router.push(`/groups/${this.feed.group.id}/posts/${this.feed.id}`)
    },
    handleLike () {
      if (this.fetching) return
      this.fetching = true
      const method = this.liked ? 'delete' : 'post'
      const url = `/plus-group/group-posts/${this.feed.id}/likes`
      this.$http({ method, url, validateStatus: s => s === 201 || s === 204 })
        .then(() => {
          if (method === 'post') {
            this.liked = true
            this.likeCount += 1
          } else {
            this.liked = false
            this.likeCount -= 1
          }
        })
        .finally(() => {
          this.fetching = false
        })
    },
    sendComment ({ reply_user: replyUser, body }) {
      if (body && body.length === 0) { return this.$Message.error('评论内容不能为空') }

      const params = {
        body,
        reply_user: replyUser,
      }
      api.postGroupPostComment(this.feed.id, params).then(comment => {
        this.commentCount += 1
        this.comments.unshift(comment)
        if (this.comments.length > 5) this.comments.pop()
        this.$Message.success('评论成功')
        this.$bus.$emit('commentInput:close', true)
      })
    },
    handleCollection () {
      api.collectGroupPost(this.feed.id, this.has_collect).then(() => {
        this.$Message.success('操作成功')
        this.has_collect = !this.has_collect
      })
    },
    handleMore () {
      const actions = []
      if (this.has_collect) {
        actions.push({
          text: '取消收藏',
          method: () => {
            api.uncollectPost(this.feed.id).then(() => {
              this.$Message.success('取消收藏')
              this.has_collect = false
            })
          },
        })
      } else {
        actions.push({
          text: '收藏',
          method: () => {
            api.collectionPost(this.feed.id).then(() => {
              this.$Message.success('已加入我的收藏')
              this.has_collect = true
            })
          },
        })
      }
      if (this.isGroupManager) {
        if (!this.pinned) {
          actions.push({
            text: '置顶帖子',
            method: () => {
              this.$bus.$emit('applyTop', {
                type: 'post-manager',
                api: api.pinnedPost,
                payload: this.feed.id,
                callback: () => {
                  this.$Message.success('置顶成功！')
                  this.$emit('reload')
                },
              })
            },
          })
        } else {
          actions.push({
            text: '撤销置顶',
            method: () => {
              const actions = [
                {
                  text: '撤销置顶',
                  method: () => {
                    this.$store
                      .dispatch('group/unpinnedPost', {
                        postId: this.feed.id,
                      })
                      .then(() => {
                        this.$Message.success('撤销置顶成功！')
                        this.$emit('reload')
                      })
                  },
                },
              ]
              setTimeout(() => {
                this.$bus.$emit(
                  'actionSheet',
                  actions,
                  this.$t('cancel'),
                  '确认撤销置顶?'
                )
              }, 200)
            },
          })
        }
      } else if (this.isMine && !this.pinned) {
        actions.push({
          text: '申请帖子置顶',
          method: () => {
            this.$bus.$emit('applyTop', {
              type: 'post',
              api: api.applyTopPost,
              payload: this.feed.id,
            })
          },
        })
      }
      if (this.isMine || this.isGroupManager) {
        // 是是自己文章或是圈子管理员
        actions.push({
          text: '删除帖子',
          method: () => {
            setTimeout(() => {
              const actions = [
                {
                  text: '删除',
                  style: { color: '#f4504d' },
                  method: () => {
                    this.$store
                      .dispatch('group/deletePost', {
                        groupId: this.feed.group.id,
                        postId: this.feed.id,
                      })
                      .then(() => {
                        this.$Message.success('删除帖子成功')
                        this.$nextTick(() => {
                          this.$el.remove()
                          this.$emit('afterDelete')
                        })
                      })
                  },
                },
              ]
              this.$bus.$emit('actionSheet', actions, this.$t('cancel'), '确认删除?')
            }, 200)
          },
        })
      }
      if (!this.isMine) {
        actions.push({
          text: '举报',
          method: () => {
            this.$bus.$emit('report', {
              type: 'post',
              payload: this.feedId,
              username: this.user.name,
              reference: this.title,
            })
          },
        })
      }

      this.$bus.$emit('actionSheet', actions)
    },
    commentAction ({ isMine = false, placeholder, reply_user: reply, comment }) {
      const actions = []
      if (isMine) {
        const isOwner = this.feed.user.id === this.CURRENTUSER.id
        actions.push({
          text: isOwner ? '评论置顶' : '申请评论置顶',
          method: () => {
            this.$bus.$emit('applyTop', {
              isOwner,
              type: 'postComment',
              api: api.applyTopPostComment,
              payload: { postId: this.feedId, commentId: comment.id },
            })
          },
        })
        actions.push({
          text: '删除评论',
          method: () => this.deleteComment(comment.id),
        })
      } else {
        actions.push({
          text: '回复',
          method: () => {
            this.handleComment({
              placeholder,
              reply_user: reply,
            })
          },
        })
        actions.push({
          text: '举报',
          method: () => {
            this.$bus.$emit('report', {
              type: 'postComment',
              payload: comment.id,
              username: comment.user.name,
              reference: comment.body,
            })
          },
        })
      }
      this.$bus.$emit('actionSheet', actions)
    },
    deleteComment (commentId) {
      this.$store
        .dispatch('group/deletePostComment', {
          postId: this.feedId,
          commentId,
        })
        .then(() => {
          this.feed.comments = this.feed.comments.filter(
            c => c.id !== commentId
          )
          this.commentCount -= 1
          this.$Message.success('删除评论成功')
        })
    },
  },
}
</script>
