package com.itsme.app.ui.wishlists
import android.view.*
import androidx.recyclerview.widget.*
import com.bumptech.glide.Glide
import com.itsme.app.R
import com.itsme.app.data.entities.WishListItem
import com.itsme.app.databinding.ItemWishListItemBinding
import java.io.File

class WishListItemAdapter(val onEdit:(WishListItem)->Unit, val onDelete:(WishListItem)->Unit) :
    ListAdapter<WishListItem, WishListItemAdapter.VH>(object: DiffUtil.ItemCallback<WishListItem>() {
        override fun areItemsTheSame(a: WishListItem, b: WishListItem) = a.id==b.id
        override fun areContentsTheSame(a: WishListItem, b: WishListItem) = a==b
    }) {
    inner class VH(val b: ItemWishListItemBinding) : RecyclerView.ViewHolder(b.root)
    override fun onCreateViewHolder(p: ViewGroup, t: Int) = VH(ItemWishListItemBinding.inflate(LayoutInflater.from(p.context), p, false))
    override fun onBindViewHolder(h: VH, pos: Int) {
        val item = getItem(pos)
        h.b.tvItemName.text = item.name
        if (!item.photoPath.isNullOrEmpty())
            Glide.with(h.b.root.context).load(File(item.photoPath)).placeholder(R.drawable.ic_image).into(h.b.ivItemPhoto)
        else h.b.ivItemPhoto.setImageResource(R.drawable.ic_image)
        h.b.btnEdit.setOnClickListener { onEdit(item) }
        h.b.btnDelete.setOnClickListener { onDelete(item) }
    }
}
