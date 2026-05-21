package com.itsme.app.ui.wishlists
import android.view.*
import androidx.recyclerview.widget.*
import com.itsme.app.data.entities.WishListHeader
import com.itsme.app.databinding.ItemWishListBinding

class WishListAdapter(val onOpen:(WishListHeader)->Unit, val onEdit:(WishListHeader)->Unit, val onDelete:(WishListHeader)->Unit, val clientName:(Int)->String) :
    ListAdapter<WishListHeader, WishListAdapter.VH>(object: DiffUtil.ItemCallback<WishListHeader>() {
        override fun areItemsTheSame(a: WishListHeader, b: WishListHeader) = a.id==b.id
        override fun areContentsTheSame(a: WishListHeader, b: WishListHeader) = a==b
    }) {
    inner class VH(val b: ItemWishListBinding) : RecyclerView.ViewHolder(b.root)
    override fun onCreateViewHolder(p: ViewGroup, t: Int) = VH(ItemWishListBinding.inflate(LayoutInflater.from(p.context), p, false))
    override fun onBindViewHolder(h: VH, pos: Int) {
        val hdr = getItem(pos)
        h.b.tvListName.text = hdr.listName
        h.b.tvClientName.text = clientName(hdr.clientId)
        h.b.root.setOnClickListener { onOpen(hdr) }
        h.b.btnEdit.setOnClickListener { onEdit(hdr) }
        h.b.btnDelete.setOnClickListener { onDelete(hdr) }
    }
}
